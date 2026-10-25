<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Booking;
use App\Model\Entity\Payment;
use App\Model\Table\BookingsTable;
use App\Service\SquareApiService;
use App\Service\Sync\BookingSyncService;
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

        /*
         * Which of these the studio cancelled itself. Square says the
         * customer did, for every one of them, so without this the list
         * blames the customer for Wendy's own cancellations.
         */
        $this->set('cancelledByStaff', array_keys(array_filter(
            $this->fetchTable('BookingCharges')->forBookings(
                array_filter($bookings->extract('square_booking_id')->toList()),
            ),
            fn($charge): bool => (bool)$charge->cancelled_by_staff,
        )));

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
     * The week on screen is refreshed from Square on the way in, which is
     * what makes an appointment booked a minute ago actually appear. Only
     * that week: a full sync is nineteen requests and the best part of
     * half a minute, which is a page load nobody would wait for.
     *
     * @return \Cake\Http\Response
     */
    public function calendar(): Response
    {
        $weekStart = $this->weekStart($this->request->getQuery('from'));
        $weekEnd = $weekStart->addDays(self::WEEK_DAYS);

        /*
         * Before reading, so the page shows what it just fetched. It
         * reports failure rather than throwing: an unreachable Square
         * should leave the studio looking at a slightly stale week, said
         * so on the page, not at an error.
         */
        $fresh = (new BookingSyncService())->refreshWindow($weekStart, $weekEnd);

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
        $this->set('fresh', $fresh);

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

        /*
         * What an offer took off when this was booked. Zero for a booking
         * made over the phone, or made before the offer started - the
         * figures are the draft's own, not today's offers.
         */
        $discountCents = array_sum(array_column($lines, 'discount_cents'));
        $payableCents = max(0, $menuCents - $discountCents);

        $this->set(compact('booking', 'lines', 'payments', 'paidPayment'));
        $this->set('customerName', $this->customerName($booking));
        $this->set('duration', $this->durationOf($booking));
        $this->set(compact('menuCents', 'discountCents'));
        // What the amount box starts at: what they were actually quoted.
        $this->set('suggestedCents', $payableCents);
        /*
         * What the payment is compared against: the price saved when it was
         * taken, so a later price change on the menu does not make an old
         * payment look short.
         */
        $this->set('expectedCents', $charge->price_cents ?? $payableCents);
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
        $this->set('statusLabel', BookingsTable::statusLabel($booking->status, (bool)$charge->cancelled_by_staff));
        $this->set('statusClass', BookingsTable::statusClass($booking->status, (bool)$charge->cancelled_by_staff));
        $this->set('paymentMethods', SquareApiService::PAYMENT_METHODS);
        $this->set('checkoutOffers', $this->checkoutOffers());
        // Recording a payment is admin-only, like the payments page itself.
        $this->set('canTakePayment', $this->isAdminIdentity($this->request->getAttribute('identity')));
        // Offered only when Square would actually accept it, so the button
        // is absent rather than there and refused.
        $this->set('canCancel', $this->isCancellable($booking));

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

        $offers = $this->checkoutOffers();
        $offerId = (string)$this->request->getData('offer_id');

        // Deleted in Square, and so by the sync, while the page sat open.
        if ($offerId !== '' && !isset($offers[$offerId])) {
            $this->Flash->error('That offer is no longer available - please choose another.');

            return $this->redirect($back);
        }

        try {
            $squarePayment = (new SquareApiService())->recordBookingPayment(
                $booking->square_booking_id,
                $amount,
                $method,
                $booking->square_customer_id,
                $booking->location_id,
                $this->paymentNote($offers[$offerId] ?? null, trim((string)$this->request->getData('note'))),
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
     * Cancels an appointment in Square, and here.
     *
     * Square is told first and only then the local row, so the calendar
     * never shows an appointment cancelled that Square still has booked -
     * and the next sync, which rebuilds this table from Square, agrees.
     *
     * Square decides what may be cancelled, and refuses an appointment that
     * is over or already cancelled. Both are checked here first so the
     * studio gets a sentence about their booking rather than an API error.
     *
     * @param string|null $id Local booking id.
     * @return \Cake\Http\Response
     */
    public function cancel(?string $id = null): Response
    {
        $this->request->allowMethod(['post']);
        $this->requireAdmin();

        $booking = $this->fetchTable('Bookings')->get($id);
        $back = ['action' => 'view', $booking->id];

        if (!$this->isCancellable($booking)) {
            $past = $booking->start_at !== null && $booking->start_at->isPast();

            $this->Flash->error($past
                ? 'That appointment has already started, so Square will not cancel it.'
                : sprintf(
                    'That appointment is already %s, so there is nothing to cancel.',
                    strtolower(BookingsTable::STATUSES[$booking->status] ?? $booking->status),
                ));

            return $this->redirect($back);
        }

        try {
            $status = (new SquareApiService())->cancelBooking($booking->square_booking_id);
        } catch (Throwable $e) {
            Log::error('Booking cancellation failed: ' . $e->getMessage());

            $this->Flash->error(
                'Square would not cancel that appointment. It may have been changed '
                . 'elsewhere - sync and try again.',
            );

            return $this->redirect($back);
        }

        /*
         * Whatever Square recorded, not what this screen would have called
         * it. Square files it under the customer unless the call carries
         * seller-level permissions, and those need an Appointments
         * subscription the studio does not have - so a status invented here
         * would be quietly rewritten by the next sync, and the booking would
         * show one thing today and another tomorrow.
         */
        $booking->status = $status ?? 'cancelled_by_customer';
        $this->fetchTable('Bookings')->save($booking);

        /*
         * Which leaves Square saying the customer did it. The truth is kept
         * on this side, on the one table a sync never touches, so the
         * screens can say who really cancelled.
         */
        $charges = $this->fetchTable('BookingCharges');
        $charge = $charges->forBooking($booking->square_booking_id);
        $charge->cancelled_by_staff = true;

        try {
            $charges->saveOrFail($charge);
        } catch (Throwable $e) {
            // The appointment is cancelled either way; losing this only
            // loses the note about who did it.
            Log::error('Could not record who cancelled a booking: ' . $e->getMessage());
        }

        $this->fetchTable('BookingDrafts')->updateAll(
            ['status' => 'cancelled'],
            ['square_booking_id' => $booking->square_booking_id],
        );

        $this->Flash->success('That appointment has been cancelled.');

        /*
         * Back to the calendar rather than the booking. A cancelled one is
         * left off the calendar, so landing there shows the slot freed up -
         * which is what someone cancelling wanted to see.
         */
        return $this->redirect(['action' => 'calendar']);
    }

    /**
     * Whether Square would accept a cancellation for this appointment.
     *
     * Only one still standing and still to come. Square refuses to cancel
     * an appointment that has already happened, and there is nothing to
     * cancel about one that was called off already.
     *
     * @param \App\Model\Entity\Booking $booking
     * @return bool
     */
    protected function isCancellable(Booking $booking): bool
    {
        if (!in_array($booking->status, ['accepted', 'pending'], true)) {
            return false;
        }

        return $booking->start_at !== null && $booking->start_at->isFuture();
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
     * The studio's Square discounts, for taking one off when paying.
     *
     * Every one, shown on the home page or not: a friends-and-family or a
     * cash discount is given at the counter without ever being advertised.
     * A discount with neither a percentage nor an amount - one Square asks
     * for on the day - has nothing to work out, so it is left out.
     *
     * @return array<string, array{title: string, label: string, percent: int|null, amount_cents: int|null}>
     */
    protected function checkoutOffers(): array
    {
        $offers = [];

        foreach ($this->fetchTable('Offers')->find()->orderBy(['Offers.title' => 'ASC'])->all() as $offer) {
            $percent = (int)$offer->discount_percent > 0 ? (int)$offer->discount_percent : null;
            // A percentage wins when both are somehow set, as on the offer card.
            $amount = $percent === null && (int)$offer->discount_amount_cents > 0
                ? (int)$offer->discount_amount_cents
                : null;

            if ($percent === null && $amount === null) {
                continue;
            }

            $offers[(string)$offer->id] = [
                'title' => (string)$offer->title,
                'label' => $percent !== null
                    ? $percent . '% off'
                    : '$' . number_format($amount / 100, 2) . ' off',
                'percent' => $percent,
                'amount_cents' => $amount,
            ];
        }

        return $offers;
    }

    /**
     * The note a payment goes to Square with: the offer taken off, if any,
     * ahead of whatever staff typed.
     *
     * Square keeps it with the payment and the sync brings it back, so the
     * offer is on record wherever the payment is looked at - this page, the
     * payments page, and Square's own dashboard.
     *
     * @param array{title: string, label: string}|null $offer
     * @param string $typed
     * @return string
     */
    protected function paymentNote(?array $offer, string $typed): string
    {
        $parts = [];

        if ($offer !== null) {
            $parts[] = 'Offer: ' . $offer['title'] . ' (' . $offer['label'] . ')';
        }

        if ($typed !== '') {
            $parts[] = $typed;
        }

        // Square refuses a payment note over 500 characters.
        return mb_substr(implode(' - ', $parts), 0, 500);
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
        /*
         * What the draft recorded, when the booking was made on this site.
         * That is where an offer's discount is kept, and it is the price the
         * customer was actually quoted - the menu may have been repriced in
         * Square since, and the offer may be over.
         */
        $quoted = $this->quotedItems($booking);

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
            $variationId = (string)($segment['service_variation_id'] ?? '');
            $service = $services[$variationId] ?? null;
            $item = $quoted[$variationId] ?? null;

            $lines[] = [
                // A treatment since taken off the menu still took up the
                // time, so it is listed rather than silently dropped.
                'name' => $item['name'] ?? ($service ? (string)$service['name'] : 'Treatment no longer on the menu'),
                'duration' => (int)($segment['duration_minutes'] ?? 0),
                'price_cents' => (int)($item['price_cents'] ?? $service['price_cents'] ?? 0),
                // Zero for a booking taken over the phone, or made before
                // offers existed: neither had an offer applied to it.
                'discount_cents' => (int)($item['discount_cents'] ?? 0),
                'offer_label' => $item['offer_label'] ?? null,
            ];
        }

        return $lines;
    }

    /**
     * The treatments as the draft recorded them, by Square variation id.
     *
     * Empty for a booking that did not come through this site's own flow -
     * one Wendy took over the phone, or made in Square herself - which has
     * no draft and so was never quoted a price here.
     *
     * @param \App\Model\Entity\Booking $booking
     * @return array<string, array<string, mixed>>
     */
    protected function quotedItems(Booking $booking): array
    {
        if (empty($booking->square_booking_id)) {
            return [];
        }

        $draft = $this->fetchTable('BookingDrafts')->find()
            ->where(['square_booking_id' => $booking->square_booking_id])
            ->first();

        if ($draft === null) {
            return [];
        }

        $items = [];

        foreach ((array)$draft->items as $item) {
            if (!empty($item['variation_id'])) {
                $items[(string)$item['variation_id']] = $item;
            }
        }

        return $items;
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
