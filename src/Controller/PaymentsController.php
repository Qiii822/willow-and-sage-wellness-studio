<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\PaymentsTable;
use Cake\Http\Response;
use Cake\I18n\FrozenTime;

class PaymentsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();

        // Payment data is financially sensitive - restrict beyond the
        // base logged-in access that Customers/Bookings allow.
        $this->requireAdmin();
    }

    /**
     * Statuses the filter offers beyond Square's own: a booking that is
     * owing, which has no payment at all - only its row here.
     */
    protected const EXTRA_STATUSES = [
        'unpaid' => 'Unpaid',
    ];

    public function index(): Response
    {
        $status = $this->request->getQuery('status');
        $startDate = $this->request->getQuery('start_date');
        $endDate = $this->request->getQuery('end_date');
        $statuses = PaymentsTable::STATUSES + self::EXTRA_STATUSES;

        // Only accept statuses we actually know about - anything else is
        // ignored rather than passed through to the query.
        if ($status !== null && !array_key_exists($status, $statuses)) {
            $status = null;
        }

        $from = $this->dayStart($startDate);
        $until = $this->dayStart($endDate)?->addDays(1);
        $startDate = $from ? $startDate : null;
        $endDate = $until ? $endDate : null;

        $rows = [];

        if ($status !== 'unpaid') {
            foreach ($this->payments($status, $from, $until) as $payment) {
                $rows[] = [
                    'type' => 'payment',
                    'date' => $payment->square_created_at,
                    'payment' => $payment,
                ];
            }
        }

        if ($status === null || $status === 'unpaid') {
            foreach ($this->unpaidBookings($from, $until) as $booking) {
                $rows[] = [
                    'type' => 'unpaid',
                    'date' => $booking->start_at,
                    'booking' => $booking,
                ];
            }
        }

        // Newest first, payments and unpaid bookings together.
        usort($rows, fn(array $a, array $b): int => ($b['date']?->getTimestamp() ?? 0) <=> ($a['date']?->getTimestamp() ?? 0));

        $this->set(compact('rows', 'status', 'startDate', 'endDate', 'statuses'));
        $this->set('linked', $this->linkedBookings($rows));

        return $this->render();
    }

    /**
     * Payments matching the filters, newest first.
     *
     * @param string|null $status
     * @param \Cake\I18n\FrozenTime|null $from
     * @param \Cake\I18n\FrozenTime|null $until
     * @return array<\App\Model\Entity\Payment>
     */
    protected function payments(?string $status, ?FrozenTime $from, ?FrozenTime $until): array
    {
        $query = $this->fetchTable('Payments')
            ->find()
            ->contain(['Customers']);

        if ($status !== null) {
            $query->where(['Payments.status' => $status]);
        }

        if ($from) {
            $query->where(['Payments.square_created_at >=' => $from]);
        }

        if ($until) {
            $query->where(['Payments.square_created_at <' => $until]);
        }

        $payments = $query->orderBy(['Payments.square_created_at' => 'DESC'])->all()->toList();

        /*
         * A payment undone from its booking was a mistake, not money that
         * changed hands, so it is left off this list - the booking's own
         * page keeps it as history. What shows here is where each booking
         * stands now: paid, or owing.
         */
        return array_values(array_filter(
            $payments,
            fn($payment): bool => !($payment->is_undone && $payment->reference_id !== null),
        ));
    }

    /**
     * Bookings that are owing: over without a payment, or marked unpaid by
     * staff and still not paid.
     *
     * @param \Cake\I18n\FrozenTime|null $from
     * @param \Cake\I18n\FrozenTime|null $until
     * @return array<\App\Model\Entity\Booking>
     */
    protected function unpaidBookings(?FrozenTime $from, ?FrozenTime $until): array
    {
        $marked = $this->fetchTable('BookingCharges')->find()
            ->select(['square_booking_id'])
            ->where(['unpaid' => true])
            ->all()
            ->extract('square_booking_id')
            ->toList();

        $bookingsTable = $this->fetchTable('Bookings');

        /*
         * Everything that could be owing: marked ones, and accepted ones that
         * have started. Whether each has actually finished and gone unpaid is
         * worked out from the payments below.
         */
        $candidates = [
            'Bookings.status' => 'accepted',
            'Bookings.start_at <' => FrozenTime::now(),
        ];

        $query = $bookingsTable->find()
            ->contain(['Customers'])
            ->where($marked === []
                ? $candidates
                : ['OR' => [$candidates, 'Bookings.square_booking_id IN' => $marked]]);

        if ($from) {
            $query->where(['Bookings.start_at >=' => $from]);
        }

        if ($until) {
            $query->where(['Bookings.start_at <' => $until]);
        }

        $bookings = $query->all()->toList();
        [, $owing] = $bookingsTable->paymentStates($bookings);

        return array_values(array_filter(
            $bookings,
            fn($booking): bool => isset($owing[$booking->square_booking_id]),
        ));
    }

    /**
     * The booking behind each row, keyed by Square booking id: its local id,
     * so the row can open it, and the price it should have cost.
     *
     * The price is the menu price saved when it was paid or marked unpaid,
     * so a later change to the menu does not make an old payment look
     * short. Older payments, taken before that was saved, fall back to
     * today's menu price - the same figure the booking page shows.
     *
     * Payments taken in Square itself have no booking, and are left out.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array{id: string, expected: int|null}>
     */
    protected function linkedBookings(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $id = $row['type'] === 'payment'
                ? $row['payment']->reference_id
                : $row['booking']->square_booking_id;

            if ($id) {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $bookingsTable = $this->fetchTable('Bookings');
        $bookings = $bookingsTable->find()
            ->where(['square_booking_id IN' => array_keys($ids)])
            ->all()
            ->toList();

        $menuPrices = $bookingsTable->menuPrices($bookings);
        $charges = $this->fetchTable('BookingCharges')->forBookings(array_keys($ids));

        $linked = [];

        foreach ($bookings as $booking) {
            $squareId = $booking->square_booking_id;
            $expected = $charges[$squareId]->price_cents ?? $menuPrices[$squareId] ?? null;

            $linked[$squareId] = [
                'id' => $booking->id,
                'expected' => $expected ?: null,
            ];
        }

        return $linked;
    }

    /**
     * The start of a day typed into the filter, or null if it is not a date.
     *
     * @param mixed $date
     * @return \Cake\I18n\FrozenTime|null
     */
    protected function dayStart(mixed $date): ?FrozenTime
    {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            return new FrozenTime($date . ' 00:00:00');
        } catch (\Exception) {
            return null;
        }
    }
}
