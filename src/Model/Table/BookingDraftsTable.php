<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\AustralianMobile;
use App\Model\Entity\BookingDraft;
use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use Cake\Utility\Security;
use Cake\Validation\Validator;

/**
 * Bookings in progress, and the record of ones that completed.
 */
class BookingDraftsTable extends Table
{
    /**
     * @param array<string, mixed> $config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('booking_drafts');
        $this->setPrimaryKey('id');

        /*
         * Converted at the database boundary rather than by accessors on
         * the entity. An accessor pair encodes on the way in and decodes on
         * the way out, but the ORM reads properties through the getter when
         * it builds the insert - so it gets the array straight back and
         * tries to store that in a text column.
         */
        $this->getSchema()->setColumnType('items', 'json');
        $this->getSchema()->setColumnType('segments', 'json');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'modified' => 'always',
                ],
            ],
        ]);
    }

    /**
     * Stores the mobile in the one form Square and an SMS gateway agree on.
     *
     * Done before validation runs, so the rule below checks the same value
     * that will be saved, and so the number leaves here as +61... however
     * the customer chose to type it.
     *
     * @param \Cake\Event\EventInterface $event
     * @param \ArrayObject $data
     * @param \ArrayObject $options
     * @return void
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        if (!isset($data['customer_phone'])) {
            return;
        }

        $normalised = AustralianMobile::normalise((string)$data['customer_phone']);

        /*
         * Left alone when it is not a mobile: the validator has to see what
         * was actually typed so it can say so, and the customer has to see
         * their own number back in the field rather than a blank.
         */
        if ($normalised !== null) {
            $data['customer_phone'] = $normalised;
        }
    }

    /**
     * @param \Cake\Validation\Validator $validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('customer_name')
            ->maxLength('customer_name', 150)
            ->notEmptyString('customer_name', 'Please tell us your name.');

        $validator
            ->email('customer_email', false, 'That email address does not look right.')
            ->maxLength('customer_email', 190)
            ->notEmptyString('customer_email', 'We need an email to send your confirmation.');

        /*
         * Every way of writing the same mobile is accepted - spaces,
         * brackets, +61, a leading zero - but the number itself has to be a
         * real Australian mobile. Square refuses the booking otherwise, and
         * it refuses it while creating the customer, so the error that comes
         * back says nothing about the number unless you dig for it. Better
         * to say "check this" under the field than to fail at the last step.
         */
        $validator
            ->scalar('customer_phone')
            ->add('customer_phone', 'australianMobile', [
                'rule' => fn($value): bool => AustralianMobile::isValid((string)$value),
                'message' => 'Enter an Australian mobile, like 0412 345 678 or +61 412 345 678.',
            ])
            ->notEmptyString('customer_phone', 'We need a mobile number in case we have to reach you.');

        $validator
            ->scalar('customer_note')
            ->maxLength('customer_note', 500)
            ->allowEmptyString('customer_note');

        return $validator;
    }

    /**
     * Starts a new draft for the treatments chosen.
     *
     * Any offer running on a treatment is written into the draft here, as a
     * figure, rather than looked up again later. What the customer was
     * quoted is a fact about this booking: the offer may be over, or
     * changed, or the treatment repriced in Square by the time anyone looks
     * at it, and none of that should move the price someone was promised.
     *
     * @param array<int, \App\Model\Entity\Service> $services
     * @param array<string, \App\Model\Entity\Offer> $offers The running
     *  offers, by the treatment each is about.
     * @return \App\Model\Entity\BookingDraft
     */
    public function start(array $services, array $offers = []): BookingDraft
    {
        $items = [];
        $subtotal = 0;
        $discount = 0;
        $offerTitles = [];

        foreach ($services as $service) {
            $menuCents = (int)$service->price_cents;

            $offer = $offers[$service->id] ?? null;
            $saving = 0;

            if ($offer !== null && $offer->has_saving) {
                $saving = max(0, $menuCents - (int)$offer->after_cents);
                $offerTitles[] = (string)$offer->title;
            }

            $items[] = [
                'service_id' => $service->id,
                'variation_id' => $service->square_variation_id,
                'name' => $service->name,
                // Still the menu price. The saving is kept beside it rather
                // than folded in, so the booking can show what it was before.
                'price_cents' => $service->price_cents,
                'duration_minutes' => $service->duration_minutes,
                'discount_cents' => $saving,
                'offer_title' => $saving > 0 ? (string)$offer->title : null,
                'offer_label' => $saving > 0 ? (string)$offer->discount_label : null,
            ];

            $subtotal += $menuCents;
            $discount += $saving;
        }

        /** @var \App\Model\Entity\BookingDraft $draft */
        $draft = $this->newEmptyEntity();

        $draft->token = bin2hex(Security::randomBytes(24));
        $draft->status = 'timing';
        $draft->items = $items;
        $draft->subtotal_cents = $subtotal;
        $draft->discount_cents = $discount;
        $draft->total_cents = $subtotal - $discount;

        /*
         * Named when one offer is responsible, so the line reads "The Willow
         * Ritual" rather than a bare "Discount". Two or more and there is no
         * single name to give it, and listing them would not fit the line.
         */
        $unique = array_unique($offerTitles);
        $draft->discount_code = count($unique) === 1 ? reset($unique) : null;

        $this->saveOrFail($draft);

        return $draft;
    }

    /**
     * Finds a draft by the token in the URL.
     *
     * @param string|null $token
     * @return \App\Model\Entity\BookingDraft|null
     */
    public function findByToken(?string $token): ?BookingDraft
    {
        if ($token === null || $token === '') {
            return null;
        }

        /** @var \App\Model\Entity\BookingDraft|null $draft */
        $draft = $this->find()->where(['token' => $token])->first();

        return $draft;
    }

    /**
     * The variation ids a draft needs availability for.
     *
     * @param \App\Model\Entity\BookingDraft $draft
     * @return array<int, string>
     */
    public function variationIds(BookingDraft $draft): array
    {
        $ids = [];

        foreach ($draft->items as $item) {
            if (!empty($item['variation_id'])) {
                $ids[] = (string)$item['variation_id'];
            }
        }

        return $ids;
    }
}
