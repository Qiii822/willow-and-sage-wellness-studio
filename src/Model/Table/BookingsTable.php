<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

/**
 * Bookings Model
 *
 * Local mirror of Square booking data, kept in sync by
 * BookingSyncService. Like Customers, this table has no direct
 * relationship to the Square API - it's populated exclusively via sync,
 * and is fully wiped and reloaded on every run rather than diffed.
 *
 * @property \App\Model\Table\CustomersTable&\Cake\ORM\Association\BelongsTo $Customers
 *
 * @method \App\Model\Entity\Booking newEmptyEntity()
 * @method \App\Model\Entity\Booking newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Booking> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Booking get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Booking patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Booking> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Booking|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Booking saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class BookingsTable extends Table
{
    /**
     * Square booking status values, mapped to their display labels.
     *
     * Single source of truth for the set of valid statuses - used for
     * validation as well as for building the status filter on the
     * bookings index page.
     */
    public const STATUSES = [
        'pending' => 'Pending',
        'cancelled_by_customer' => 'Cancelled By Customer',
        'cancelled_by_seller' => 'Cancelled By Seller',
        'declined' => 'Declined',
        'accepted' => 'Accepted',
        'no_show' => 'No Show',
    ];

    /**
     * What to call a booking's status on screen.
     *
     * Square files every cancellation this app makes under the customer,
     * whoever really did it: saying otherwise needs seller-level
     * permissions, and those need an Appointments subscription the studio
     * does not have. So when the dashboard was the one that cancelled it,
     * that is remembered on this side and said here - otherwise the
     * studio cancels an appointment and the screen blames the customer.
     *
     * One place for it, because the list and the booking page both show
     * this and the two must not end up saying different things.
     *
     * @param string $status
     * @param bool $cancelledByStaff Whether the studio cancelled it here.
     * @return string
     */
    public static function statusLabel(string $status, bool $cancelledByStaff = false): string
    {
        if ($cancelledByStaff && str_starts_with($status, 'cancelled')) {
            return 'Cancelled By Studio';
        }

        return self::STATUSES[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    /**
     * The badge class that goes with that label.
     *
     * Cancelled is cancelled whoever did it, so a studio cancellation
     * borrows the seller styling rather than needing one of its own.
     *
     * @param string $status
     * @param bool $cancelledByStaff
     * @return string
     */
    public static function statusClass(string $status, bool $cancelledByStaff = false): string
    {
        if ($cancelledByStaff && str_starts_with($status, 'cancelled')) {
            return 'cancelled-by-seller';
        }

        return str_replace('_', '-', $status);
    }

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('bookings');
        $this->setPrimaryKey('id');

        // Joined for display purposes only (e.g. showing a customer's name
        // on the bookings list) - not a real DB foreign key, since the
        // customers table is fully wiped and reloaded on every sync and
        // couldn't sustain one.
        $this->belongsTo('Customers', [
            'foreignKey' => 'square_customer_id',
            'bindingKey' => 'square_customer_id',
        ]);

        // Auto-populates created_at on insert and updated_at on every
        // save - neither is set explicitly by BookingSyncService.
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('square_booking_id')
            ->maxLength('square_booking_id', 255)
            ->requirePresence('square_booking_id', 'create')
            ->notEmptyString('square_booking_id');

        $validator
            ->scalar('status')
            ->inList('status', array_keys(self::STATUSES))
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        $validator
            ->scalar('location_id')
            ->maxLength('location_id', 255)
            ->allowEmptyString('location_id');

        $validator
            ->scalar('square_customer_id')
            ->maxLength('square_customer_id', 255)
            ->allowEmptyString('square_customer_id');

        $validator
            ->dateTime('start_at')
            ->allowEmptyDateTime('start_at');

        $validator
            ->scalar('customer_note')
            ->allowEmptyString('customer_note');

        $validator
            ->scalar('seller_note')
            ->allowEmptyString('seller_note');

        $validator
            ->scalar('appointment_segments')
            ->allowEmptyString('appointment_segments');

        $validator
            ->integer('square_version')
            ->requirePresence('square_version', 'create')
            ->notEmptyString('square_version');

        $validator
            ->dateTime('square_updated_at')
            ->allowEmptyDateTime('square_updated_at');

        $validator
            ->dateTime('square_created_at')
            ->allowEmptyDateTime('square_created_at');

        return $validator;
    }

    /**
     * Finds bookings for a given Square customer ID, most recent first.
     *
     * @param string $squareCustomerId
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findBySquareCustomerId(string $squareCustomerId)
    {
        return $this->find()
            ->where(['square_customer_id' => $squareCustomerId])
            ->orderByDesc('start_at');
    }

    /**
     * Which bookings have been paid for, and which are owing.
     *
     * Paid means a payment that went through and was not undone, either:
     * - recorded from the booking page, which tags it with the booking id, or
     * - taken in Square itself (at the counter, say) from the same customer
     *   on the day of the appointment. Square has no other link between a
     *   payment and a booking, and without this every booking paid before
     *   the booking page could take payments would show as owing.
     *
     * Owing means not paid, and either marked unpaid by staff or already
     * over - an appointment that has happened should have been paid for.
     *
     * @param array<\App\Model\Entity\Booking> $bookings
     * @return array{0: array<string, true>, 1: array<string, true>} Paid and
     *  owing, each keyed by Square booking id.
     */
    public function paymentStates(array $bookings): array
    {
        if ($bookings === []) {
            return [[], []];
        }

        $locator = TableRegistry::getTableLocator();
        $paymentsTable = $locator->get('Payments');
        $ids = array_map(fn($booking): string => $booking->square_booking_id, $bookings);

        $paid = [];

        foreach ($paymentsTable->find()->where(['reference_id IN' => $ids])->all() as $payment) {
            if ($payment->is_paid) {
                $paid[(string)$payment->reference_id] = true;
            }
        }

        // Same customer, same day, for the bookings not already settled.
        $unmatched = array_filter(
            $bookings,
            fn($booking): bool => !isset($paid[$booking->square_booking_id])
                && $booking->square_customer_id !== null
                && $booking->start_at !== null,
        );

        if ($unmatched !== []) {
            $customerIds = array_unique(array_map(fn($booking) => $booking->square_customer_id, $unmatched));
            $paidDays = [];

            $payments = $paymentsTable->find()
                ->where([
                    'square_customer_id IN' => $customerIds,
                    // One recorded for a booking settles that booking only.
                    'reference_id IS' => null,
                ])
                ->all();

            foreach ($payments as $payment) {
                if ($payment->is_paid && $payment->square_created_at) {
                    $paidDays[$payment->square_customer_id . '|' . $payment->square_created_at->format('Y-m-d')] = true;
                }
            }

            foreach ($unmatched as $booking) {
                if (isset($paidDays[$booking->square_customer_id . '|' . $booking->start_at->format('Y-m-d')])) {
                    $paid[$booking->square_booking_id] = true;
                }
            }
        }

        $charges = $locator->get('BookingCharges')->forBookings($ids);
        $owing = [];

        foreach ($bookings as $booking) {
            $squareId = $booking->square_booking_id;

            if (isset($paid[$squareId])) {
                continue;
            }

            if ($booking->is_finished || ($charges[$squareId]->unpaid ?? false)) {
                $owing[$squareId] = true;
            }
        }

        return [$paid, $owing];
    }

    /**
     * Today's menu price of each booking, from the treatments in it.
     *
     * One query for the whole menu, however many bookings are priced. A
     * treatment no longer on the menu counts as nothing rather than failing
     * the whole booking.
     *
     * @param iterable<\App\Model\Entity\Booking> $bookings
     * @return array<string, int> Cents, keyed by Square booking id.
     */
    public function menuPrices(iterable $bookings): array
    {
        $prices = [];

        foreach (TableRegistry::getTableLocator()->get('Services')->find()
            ->select(['square_variation_id', 'price_cents'])
            ->where(['square_variation_id IS NOT' => null])
            ->disableHydration()
            ->all() as $row) {
            $prices[(string)$row['square_variation_id']] = (int)$row['price_cents'];
        }

        $totals = [];

        foreach ($bookings as $booking) {
            $total = 0;

            foreach ($booking->appointment_segments_array as $segment) {
                $total += $prices[$segment['service_variation_id'] ?? ''] ?? 0;
            }

            $totals[$booking->square_booking_id] = $total;
        }

        return $totals;
    }

    /**
     * Finds a given Square customer's upcoming bookings (start_at in the
     * future), soonest first.
     *
     * @param string $squareCustomerId
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findUpcomingBySquareCustomerId(string $squareCustomerId)
    {
        return $this->find()
            ->where([
                'square_customer_id' => $squareCustomerId,
                'start_at >=' => DateTime::now(),
            ])
            ->orderByAsc('start_at');
    }
}
