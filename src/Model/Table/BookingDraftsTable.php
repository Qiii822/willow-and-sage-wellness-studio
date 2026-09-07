<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\BookingDraft;
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
         * Deliberately loose: people write numbers with spaces, brackets,
         * +61 or a leading 0, and rejecting any of those is more likely to
         * turn away a real customer than to catch a wrong number. It is
         * normalised before use rather than refused here.
         */
        $validator
            ->scalar('customer_phone')
            ->add('customer_phone', 'enoughDigits', [
                'rule' => fn ($value): bool => strlen(preg_replace('/\D/', '', (string)$value)) >= 8,
                'message' => 'That phone number looks too short.',
            ])
            ->notEmptyString('customer_phone', 'We need a phone number in case we have to reach you.');

        $validator
            ->scalar('customer_note')
            ->maxLength('customer_note', 500)
            ->allowEmptyString('customer_note');

        return $validator;
    }

    /**
     * Starts a new draft for the treatments chosen.
     *
     * @param array<int, \App\Model\Entity\Service> $services
     * @return \App\Model\Entity\BookingDraft
     */
    public function start(array $services): BookingDraft
    {
        $items = [];
        $subtotal = 0;

        foreach ($services as $service) {
            $items[] = [
                'service_id' => $service->id,
                'variation_id' => $service->square_variation_id,
                'name' => $service->name,
                'price_cents' => $service->price_cents,
                'duration_minutes' => $service->duration_minutes,
            ];

            $subtotal += (int)$service->price_cents;
        }

        /** @var \App\Model\Entity\BookingDraft $draft */
        $draft = $this->newEmptyEntity();

        $draft->token = bin2hex(Security::randomBytes(24));
        $draft->status = 'timing';
        $draft->items = $items;
        $draft->subtotal_cents = $subtotal;
        $draft->discount_cents = 0;
        $draft->total_cents = $subtotal;

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
