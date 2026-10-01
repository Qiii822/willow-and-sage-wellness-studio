<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Booking;
use App\Model\Entity\Payment;
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
        [$paid, $unpaid] = $this->fetchTable('Bookings')->paymentStates($bookings->toList());
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
                'unpaid' => isset($unpaid[$booking->square_booking_id]),
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
        $paidPayment = $this->paidPayment($payments);
        $charge = $this->fetchTable('BookingCharges')->forBooking($booking->square_booking_id);
        $menuCents = array_sum(array_column($lines, 'price_cents'));

        $this->set(compact('booking', 'lines', 'payments', 'paidPayment'));
        $this->set('customerName', $this->customerName($booking));
        $this->set('duration', $this->durationOf($booking));
        $this->set('suggestedCents', $menuCents);
        /*
         * What the payment is compared against: the menu price saved when it
         * was taken, so a later price change on the menu does not make an
         * old payment look short.
         */
        $this->set('expectedCents', $charge->price_cents ?? $menuCents);
        [$paid, $owing] = $this->fetchTable('Bookings')->paymentStates([$booking]);

        $this->set('isPaid', $paidPayment !== null);
        // Paid at the counter in Square, matched by customer and day rather
        // than recorded here - so there is nothing on this page to undo.
        $this->set('isPaidInSquare', $paidPayment === null && isset($paid[$booking->square_booking_id]));
        $this->set('isUnpaid', isset($owing[$booking->square_booking_id]));
        // Only a mark staff set can be cleared; an appointment that is over
        // stays owing until it is paid.
        $this->set('isMarkedUnpaid', $charge->unpaid && !$booking->is_finished);
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

        $existing = $this->paymentsFor($booking);

        if ($this->paidPayment($existing) !== null) {
            $this->Flash->error('This booking has already been paid for.');

            return $this->redirect($back);
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
                count($existing),
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

        /*
         * Square answering with a payment that has already been refunded
         * means it treated this as a repeat of an earlier one rather than a
         * new payment. Saying "recorded" would be wrong - nothing new was.
         */
        $refunded = (int)$squarePayment->getRefundedMoney()?->getAmount();

        if ($refunded > 0 && $refunded >= (int)$squarePayment->getAmountMoney()?->getAmount()) {
            Log::error('Recording booking payment returned an already refunded payment: ' . $squarePayment->getId());
            $this->Flash->error('Square did not take a new payment - please refresh the page and try again.');

            return $this->redirect($back);
        }

        $this->saveLocally($squarePayment);

        // The menu price it was paid against, and no longer owing.
        $this->saveCharge($booking, false);

        $this->Flash->success(sprintf(
            'Payment of $%s recorded for %s.',
            number_format($amount / 100, 2),
            $this->customerName($booking),
        ));

        return $this->redirect(['controller' => 'Payments', 'action' => 'index']);
    }

    /**
     * Undoes a payment recorded against the wrong booking, or before the
     * customer had actually paid.
     *
     * Square will not delete a completed payment, so it is refunded in full
     * there; the booking then reads as not paid again and can be paid for
     * properly.
     *
     * @param string|null $id Local booking id.
     * @return \Cake\Http\Response
     */
    public function undo(?string $id = null): Response
    {
        $this->request->allowMethod(['post']);
        $this->requireAdmin();

        $booking = $this->fetchTable('Bookings')->get($id);
        $back = ['action' => 'view', $booking->id];
        $payment = $this->paidPayment($this->paymentsFor($booking));

        if ($payment === null) {
            $this->Flash->error('There is no payment on this booking to refund.');

            return $this->redirect($back);
        }

        try {
            $squarePayment = (new SquareApiService())->undoBookingPayment(
                $payment->square_payment_id,
                $payment->amount,
                $payment->source_type,
            );
        } catch (Throwable $e) {
            Log::error('Undoing booking payment failed: ' . $e->getMessage());

            $this->Flash->error(
                'The payment could not be refunded in Square just now. It is still recorded - please try again.',
            );

            return $this->redirect($back);
        }

        $this->saveLocally($squarePayment);

        /*
         * Square can report a refund as pending for a moment before it adds
         * it to the payment. It has accepted it, so the local row shows it
         * undone now rather than paid until the next sync.
         */
        $local = $this->fetchTable('Payments')->find()
            ->where(['square_payment_id' => $payment->square_payment_id])
            ->first();

        if ($local && !$local->is_undone) {
            $local->refunded_amount = $local->amount;
            $this->fetchTable('Payments')->save($local);
        }

        $this->Flash->success(sprintf(
            'Payment of $%s refunded. The booking is not paid any more.',
            number_format($payment->amount / 100, 2),
        ));

        return $this->redirect($back);
    }

    /**
     * Marks a booking as owing - the customer left without paying, say - or
     * clears that mark.
     *
     * Kept locally rather than in Square, which has nowhere to record it.
     * It shows in red on the calendar and the payments page until a payment
     * is recorded, which clears it.
     *
     * @param string|null $id Local booking id.
     * @return \Cake\Http\Response
     */
    public function unpaid(?string $id = null): Response
    {
        $this->request->allowMethod(['post']);
        $this->requireAdmin();

        $booking = $this->fetchTable('Bookings')->get($id);
        $owing = (bool)$this->request->getData('unpaid');

        if ($owing && $this->paidPayment($this->paymentsFor($booking)) !== null) {
            $this->Flash->error('This booking has been paid for. Refund the payment first if that was a mistake.');

            return $this->redirect(['action' => 'view', $booking->id]);
        }

        $this->saveCharge($booking, $owing);

        $this->Flash->success($owing ? 'Marked as unpaid.' : 'No longer marked as unpaid.');

        return $this->redirect(['action' => 'view', $booking->id]);
    }

    /**
     * Saves the booking's menu price and whether it is owing.
     *
     * @param \App\Model\Entity\Booking $booking
     * @param bool $unpaid
     * @return void
     */
    protected function saveCharge(Booking $booking, bool $unpaid): void
    {
        $charges = $this->fetchTable('BookingCharges');
        $charge = $charges->forBooking($booking->square_booking_id);

        $charge->unpaid = $unpaid;
        $charge->price_cents = array_sum(array_column($this->lineItems($booking), 'price_cents'));

        try {
            $charges->saveOrFail($charge);
        } catch (Throwable $e) {
            // Losing this only loses the price comparison or the red mark,
            // never the payment itself, so it is logged rather than shown.
            Log::warning('Could not save booking charge: ' . $e->getMessage());
        }
    }

    /**
     * The payment that settles a booking, if any - one that went through
     * and has not been undone.
     *
     * @param array<\App\Model\Entity\Payment> $payments
     * @return \App\Model\Entity\Payment|null
     */
    protected function paidPayment(array $payments): ?Payment
    {
        foreach ($payments as $payment) {
            if ($payment->is_paid) {
                return $payment;
            }
        }

        return null;
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
