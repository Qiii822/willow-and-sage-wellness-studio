<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Booking;
use App\Model\Table\BookingsTable;
use App\Service\SquareApiService;
use App\Service\Sync\PaymentSyncService;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Throwable;

class BookingsController extends AppController
{
    /** Sunday to Saturday, the way Square lays its own calendar out. */
    protected const WEEK_DAYS = 7;

    /**
     * The hours the grid shows before any booking stretches it. Wide enough
     * to cover the studio's day without the page opening on empty rows.
     */
    protected const DEFAULT_FIRST_HOUR = 8;

    protected const DEFAULT_LAST_HOUR = 19;

    /**
     * Payment statuses that mean the booking has been paid for. A failed or
     * cancelled payment leaves it still owing.
     */
    protected const PAID_STATUSES = ['completed', 'approved'];

    /**
     * The most a single booking payment can be, in cents. Well above any
     * treatment on the menu - it is there to catch a slipped key turning
     * $85 into $8500, not to cap a real bill.
     */
    protected const MAX_PAYMENT_CENTS = 500000;

    public function index(): Response
    {
        $status = $this->request->getQuery('status');
        $startDate = $this->request->getQuery('start_date');
        $endDate = $this->request->getQuery('end_date');

        // Only accept statuses we actually know about - anything else is
        // ignored rather than passed through to the query.
        if ($status !== null && !array_key_exists($status, BookingsTable::STATUSES)) {
            $status = null;
        }

        $query = $this->fetchTable('Bookings')
            ->find()
            ->contain(['Customers']);

        if ($status !== null) {
            $query->where(['Bookings.status' => $status]);
        }

        if ($startDate) {
            try {
                $query->where(['Bookings.start_at >=' => new DateTime($startDate . ' 00:00:00')]);
            } catch (\Exception) {
                $startDate = null;
            }
        }

        if ($endDate) {
            try {
                $query->where(['Bookings.start_at <' => (new DateTime($endDate . ' 00:00:00'))->addDays(1)]);
            } catch (\Exception) {
                $endDate = null;
            }
        }

        $bookings = $query
            ->orderBy([
                'start_at' => 'DESC',
            ])
            ->all();

        $this->set(compact('bookings', 'status', 'startDate', 'endDate'));
        $this->set('statuses', BookingsTable::STATUSES);

        return $this->render();
    }

    /**
     * The week's bookings laid out as a calendar.
     *
     * The same rows the list shows, arranged by when they actually are.
     * A list answers "what is booked"; a studio's real question is "what
     * does Friday look like", and only a grid answers that.
     *
     * Everything comes from the local mirror of Square, so this is as
     * current as the last sync - it is not a second, competing calendar.
     *
     * @return \Cake\Http\Response
     */
    public function calendar(): Response
    {
        $weekStart = $this->weekStart($this->request->getQuery('from'));
        $weekEnd = $weekStart->addDays(self::WEEK_DAYS);

        $bookings = $this->fetchTable('Bookings')
            ->find()
            ->contain(['Customers'])
            ->where([
                'Bookings.start_at >=' => $weekStart,
                'Bookings.start_at <' => $weekEnd,
                /*
                 * Only what is still going ahead. A cancelled appointment
                 * is a row in the list, but on a calendar it is a block of
                 * time that looks taken when it is free.
                 */
                'Bookings.status IN' => ['accepted', 'pending'],
            ])
            ->orderByAsc('Bookings.start_at')
            ->all();

        $treatments = $this->treatmentNames();
        $paid = $this->paidBookingIds($bookings->toList());
        $days = array_fill(0, self::WEEK_DAYS, []);
        $earliest = self::DEFAULT_FIRST_HOUR * 60;
        $latest = self::DEFAULT_LAST_HOUR * 60;

        foreach ($bookings as $booking) {
            if ($booking->start_at === null) {
                continue;
            }

            $column = (int)$weekStart->diffInDays($booking->start_at, false);

            if ($column < 0 || $column >= self::WEEK_DAYS) {
                continue;
            }

            $minutes = ((int)$booking->start_at->format('G') * 60)
                + (int)$booking->start_at->format('i');
            $duration = $this->durationOf($booking);

            $days[$column][] = [
                'booking' => $booking,
                'name' => $this->customerName($booking),
                'treatments' => $this->treatmentsOf($booking, $treatments),
                'start_minutes' => $minutes,
                'duration' => $duration,
                'label' => $booking->start_at->format('g:i a'),
                'paid' => isset($paid[$booking->square_booking_id]),
            ];

            // The grid stretches to whatever is actually booked, so an
            // early start or a late finish is never cropped off it.
            $earliest = min($earliest, $minutes);
            $latest = max($latest, $minutes + $duration);
        }

        // Whole hours, so the times down the side read as a clock.
        $firstHour = intdiv($earliest, 60);
        $lastHour = (int)ceil($latest / 60);

        $this->set(compact('days', 'firstHour', 'lastHour'));
        $this->set('weekStart', $weekStart);
        $this->set('previous', $weekStart->subDays(self::WEEK_DAYS)->format('Y-m-d'));
        $this->set('next', $weekStart->addDays(self::WEEK_DAYS)->format('Y-m-d'));
        $this->set('today', DateTime::now()->format('Y-m-d'));
        $this->set('count', count($bookings));

        return $this->render();
    }

    /**
     * One booking: who, when, what, and whether it has been paid for.
     *
     * Opened from a block on the calendar. Staff who can see payments can
     * also record one here, once the customer has paid at the studio.
     *
     * @param string|null $id Local booking id.
     * @return \Cake\Http\Response
     */
    public function view(?string $id = null): Response
    {
        $booking = $this->fetchTable('Bookings')->get($id, contain: ['Customers']);

        $lines = $this->lineItems($booking);
        $payments = $this->paymentsFor($booking);

        $paid = array_filter(
            $payments,
            fn($payment): bool => in_array($payment->status, self::PAID_STATUSES, true),
        );

        $this->set(compact('booking', 'lines', 'payments'));
        $this->set('customerName', $this->customerName($booking));
        $this->set('duration', $this->durationOf($booking));
        $this->set('suggestedCents', array_sum(array_column($lines, 'price_cents')));
        $this->set('isPaid', $paid !== []);
        $this->set('statuses', BookingsTable::STATUSES);
        $this->set('paymentMethods', SquareApiService::PAYMENT_METHODS);
        // Recording a payment is admin-only, like the payments page itself.
        $this->set('canTakePayment', $this->isAdminIdentity($this->request->getAttribute('identity')));

        return $this->render();
    }

    /**
     * Records that a booking has been paid for, then shows it on the
     * payments page.
     *
     * The payment is written to Square first and only then to the local
     * table, so the payments page never shows one Square does not have -
     * and the next sync, which rebuilds that table from Square, keeps it.
     *
     * @param string|null $id Local booking id.
     * @return \Cake\Http\Response
     */
    public function pay(?string $id = null): Response
    {
        $this->request->allowMethod(['post']);
        $this->requireAdmin();

        $booking = $this->fetchTable('Bookings')->get($id, contain: ['Customers']);
        $back = ['action' => 'view', $booking->id];

        foreach ($this->paymentsFor($booking) as $payment) {
            if (in_array($payment->status, self::PAID_STATUSES, true)) {
                $this->Flash->error('This booking has already been paid for.');

                return $this->redirect($back);
            }
        }

        $amount = $this->parseAmount((string)$this->request->getData('amount'));
        $method = (string)$this->request->getData('method');

        if ($amount === null) {
            $this->Flash->error('Please enter the amount paid, in dollars - for example 85 or 85.50.');

            return $this->redirect($back);
        }

        if (!isset(SquareApiService::PAYMENT_METHODS[$method])) {
            $this->Flash->error('Please choose how the customer paid.');

            return $this->redirect($back);
        }

        try {
            $squarePayment = (new SquareApiService())->recordBookingPayment(
                $booking->square_booking_id,
                $amount,
                $method,
                $booking->square_customer_id,
                $booking->location_id,
                trim((string)$this->request->getData('note')),
            );
        } catch (Throwable $e) {
            Log::error('Recording booking payment failed: ' . $e->getMessage());

            /*
             * The one failure staff can do something about is named: the
             * Square connection predates the permission this needs, and
             * reconnecting is the fix.
             */
            $this->Flash->error(
                str_contains($e->getMessage(), 'INSUFFICIENT_SCOPES')
                    ? 'Square has not given this app permission to record payments yet. '
                        . 'Disconnect and reconnect Square in Settings, then try again.'
                    : 'The payment could not be recorded in Square just now. Nothing was saved - please try again.',
            );

            return $this->redirect($back);
        }

        $this->saveLocally($squarePayment);

        $this->Flash->success(sprintf(
            'Payment of $%s recorded for %s.',
            number_format($amount / 100, 2),
            $this->customerName($booking),
        ));

        return $this->redirect(['controller' => 'Payments', 'action' => 'index']);
    }

    /**
     * Writes a payment Square has just accepted into the local table.
     *
     * The next sync would bring it in anyway; this is so it is on the
     * payments page the moment staff land there. A retry that Square
     * answered with the same payment is not written twice.
     *
     * @param \Square\Types\Payment $squarePayment
     * @return void
     */
    protected function saveLocally(\Square\Types\Payment $squarePayment): void
    {
        $payments = $this->fetchTable('Payments');
        $data = (new PaymentSyncService())->mapPayment($squarePayment);

        $existing = $payments->find()
            ->where(['square_payment_id' => $data['square_payment_id']])
            ->first();

        try {
            $payments->saveOrFail($existing
                ? $payments->patchEntity($existing, $data)
                : $payments->newEntity($data));
        } catch (Throwable $e) {
            // Square has it, so it is not lost - the next sync fills it in.
            Log::warning('Booking payment saved in Square but not locally: ' . $e->getMessage());
        }
    }

    /**
     * Which of these bookings have been paid for.
     *
     * One query for the whole week rather than one per block.
     *
     * @param array<\App\Model\Entity\Booking> $bookings
     * @return array<string, true> Keyed by Square booking id.
     */
    protected function paidBookingIds(array $bookings): array
    {
        $ids = array_map(fn(Booking $booking): string => $booking->square_booking_id, $bookings);

        if ($ids === []) {
            return [];
        }

        $rows = $this->fetchTable('Payments')->find()
            ->select(['reference_id'])
            ->where([
                'reference_id IN' => $ids,
                'status IN' => self::PAID_STATUSES,
            ])
            ->disableHydration()
            ->all();

        $paid = [];

        foreach ($rows as $row) {
            $paid[(string)$row['reference_id']] = true;
        }

        return $paid;
    }

    /**
     * Payments recorded against a booking, newest first.
     *
     * @param \App\Model\Entity\Booking $booking
     * @return array<\App\Model\Entity\Payment>
     */
    protected function paymentsFor(Booking $booking): array
    {
        return $this->fetchTable('Payments')->find()
            ->where(['reference_id' => $booking->square_booking_id])
            ->orderByDesc('square_created_at')
            ->all()
            ->toList();
    }

    /**
     * A dollar amount as typed, in cents.
     *
     * Takes "85", "85.5", "85.50" or "$85.50". Anything else - a negative,
     * a third decimal place, a stray letter - is refused rather than
     * guessed at, since this becomes a figure in the studio's books.
     *
     * @param string $typed
     * @return int|null Cents, or null if it is not a usable amount.
     */
    protected function parseAmount(string $typed): ?int
    {
        $typed = str_replace([',', ' '], '', trim($typed));

        if (!preg_match('/^\$?(\d+)(?:\.(\d{1,2}))?$/', $typed, $match)) {
            return null;
        }

        $cents = ((int)$match[1] * 100) + (int)str_pad($match[2] ?? '0', 2, '0');

        return $cents > 0 && $cents <= self::MAX_PAYMENT_CENTS ? $cents : null;
    }

    /**
     * The treatments in a booking, each with its menu price.
     *
     * @param \App\Model\Entity\Booking $booking
     * @return array<int, array{name: string, duration: int, price_cents: int}>
     */
    protected function lineItems(Booking $booking): array
    {
        $rows = $this->fetchTable('Services')->find()
            ->select(['square_variation_id', 'name', 'price_cents'])
            ->where(['square_variation_id IS NOT' => null])
            ->disableHydration()
            ->all();

        $services = [];

        foreach ($rows as $row) {
            $services[(string)$row['square_variation_id']] = $row;
        }

        $lines = [];

        foreach ($booking->appointment_segments_array as $segment) {
            $service = $services[$segment['service_variation_id'] ?? ''] ?? null;

            $lines[] = [
                // A treatment since taken off the menu still took up the
                // time, so it is listed rather than silently dropped.
                'name' => $service ? (string)$service['name'] : 'Treatment no longer on the menu',
                'duration' => (int)($segment['duration_minutes'] ?? 0),
                'price_cents' => (int)($service['price_cents'] ?? 0),
            ];
        }

        return $lines;
    }

    /**
     * The Sunday on or before the requested date, or of this week.
     *
     * Sunday first because that is how Square's own calendar is laid out,
     * and the studio reads both.
     *
     * @param mixed $requested
     * @return \Cake\I18n\DateTime
     */
    protected function weekStart(mixed $requested): DateTime
    {
        $day = DateTime::now();

        if (is_string($requested) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested)) {
            try {
                $day = new DateTime($requested . ' 00:00:00');
            } catch (\Exception) {
                // A mistyped date in the address bar lands on this week
                // rather than on an error page.
                $day = DateTime::now();
            }
        }

        return $day->startOfDay()->subDays((int)$day->format('w'));
    }

    /**
     * How long a booking runs, from the segments Square sent.
     *
     * @param \App\Model\Entity\Booking $booking
     * @return int Minutes.
     */
    protected function durationOf(Booking $booking): int
    {
        $minutes = 0;

        foreach ($booking->appointment_segments_array as $segment) {
            $minutes += (int)($segment['duration_minutes'] ?? 0);
        }

        // Square can leave the duration off; an hour keeps the block
        // visible and roughly right rather than collapsing it to nothing.
        return $minutes > 0 ? $minutes : 60;
    }

    /**
     * Treatment names by the variation id Square books them as.
     *
     * @return array<string, string>
     */
    protected function treatmentNames(): array
    {
        $rows = $this->fetchTable('Services')->find()
            ->select(['square_variation_id', 'name'])
            ->where(['square_variation_id IS NOT' => null])
            ->disableHydration()
            ->all();

        $names = [];

        foreach ($rows as $row) {
            $names[(string)$row['square_variation_id']] = (string)$row['name'];
        }

        return $names;
    }

    /**
     * @param \App\Model\Entity\Booking $booking
     * @param array<string, string> $names
     * @return array<int, string>
     */
    protected function treatmentsOf(Booking $booking, array $names): array
    {
        $treatments = [];

        foreach ($booking->appointment_segments_array as $segment) {
            $id = $segment['service_variation_id'] ?? null;

            if ($id !== null && isset($names[$id])) {
                $treatments[] = $names[$id];
            }
        }

        return $treatments;
    }

    /**
     * @param \App\Model\Entity\Booking $booking
     * @return string
     */
    protected function customerName(Booking $booking): string
    {
        if (!$booking->customer) {
            return 'Customer';
        }

        $name = trim(
            ($booking->customer->given_name ?? '') . ' '
            . ($booking->customer->family_name ?? ''),
        );

        return $name !== '' ? $name : 'Customer';
    }
}
