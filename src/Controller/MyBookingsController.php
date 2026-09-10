<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\BookingNotifier;
use App\Service\CustomerBookingLookup;
use App\Service\SquareApiService;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use DateTimeZone;
use Throwable;

/**
 * My Bookings Controller
 *
 * Where a customer sees, cancels or moves their own appointments.
 *
 * There are no accounts on this site, so the phone number they booked with
 * is the only thing they reliably have. A number on its own cannot be the
 * key - anyone who knows it could read someone's appointments and cancel
 * them - so it is proved with a one-time code before anything is shown.
 *
 * @property \App\Model\Table\BookingAccessCodesTable $BookingAccessCodes
 */
class MyBookingsController extends AppController
{
    use BookingCalendarTrait;

    /** How long a proved number stays proved. */
    protected const SESSION_MINUTES = 30;

    /** Where the verified number is kept. */
    protected const SESSION_KEY = 'MyBookings.phone';

    /** When that proof runs out. */
    protected const SESSION_EXPIRY_KEY = 'MyBookings.expires';

    /**
     * Loaded by hand: the controller is named for the page, not for a table.
     */
    protected Table $BookingAccessCodes;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->BookingAccessCodes = $this->fetchTable('BookingAccessCodes');
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            'index',
            'verify',
            'signOut',
            'cancel',
            'reschedule',
        ]);

        $this->viewBuilder()->setLayout('site');
    }

    /**
     * Ask for the number, or list what it has.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index()
    {
        $phone = $this->verifiedPhone();

        if ($phone !== null) {
            $this->set('phone', $phone);
            $this->set('bookings', (new CustomerBookingLookup())->upcoming($phone));
            $this->set('timezone', $this->timezone());

            return $this->render('list');
        }

        if ($this->request->is('post')) {
            $entered = trim((string)$this->request->getData('phone'));

            if (strlen(preg_replace('/\D/', '', $entered) ?? '') < 8) {
                $this->Flash->error('Please enter the mobile number you booked with.');
            } else {
                return $this->sendCode($entered);
            }
        }
    }

    /**
     * Type the code that was sent.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function verify()
    {
        $phone = (string)$this->request->getSession()->read('MyBookings.pending');

        if ($phone === '') {
            return $this->redirect(['action' => 'index']);
        }

        if ($this->request->is('post')) {
            if ($this->request->getData('resend') !== null) {
                return $this->sendCode($phone);
            }

            $code = (string)$this->request->getData('code');

            if ($this->BookingAccessCodes->verify($phone, $code)) {
                $session = $this->request->getSession();
                $session->write(self::SESSION_KEY, $phone);
                $session->write(
                    self::SESSION_EXPIRY_KEY,
                    DateTime::now()->addMinutes(self::SESSION_MINUTES)->getTimestamp(),
                );
                $session->delete('MyBookings.pending');
                $session->delete('MyBookings.shownCode');

                return $this->redirect(['action' => 'index']);
            }

            $this->Flash->error('That code was not right, or it has expired. Ask for a new one.');
        }

        $this->set('phone', $phone);
        // Only ever set in development, where the code is not texted anywhere.
        $this->set('shownCode', $this->request->getSession()->read('MyBookings.shownCode'));
    }

    /**
     * Forget the number.
     *
     * @return \Cake\Http\Response
     */
    public function signOut(): Response
    {
        $session = $this->request->getSession();
        $session->delete('MyBookings');

        $this->Flash->success('You have been signed out.');

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Cancel one appointment.
     *
     * @param string|null $bookingId
     * @return \Cake\Http\Response
     */
    public function cancel(?string $bookingId = null): Response
    {
        $this->request->allowMethod('post');

        $booking = $this->ownedBooking($bookingId);

        if ($booking === null) {
            return $this->redirect(['action' => 'index']);
        }

        try {
            (new SquareApiService())->cancelBooking((string)$bookingId);
        } catch (Throwable $e) {
            Log::error('Booking cancellation failed: ' . $e->getMessage());

            $this->Flash->error(
                'We could not cancel that appointment. Please call the studio on (08) 8395 5966.',
            );

            return $this->redirect(['action' => 'index']);
        }

        $this->markCancelledLocally($bookingId);

        $this->Flash->success('That appointment has been cancelled.');

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Move one appointment to another time.
     *
     * @param string|null $bookingId
     * @return \Cake\Http\Response|null|void
     */
    public function reschedule(?string $bookingId = null)
    {
        $booking = $this->ownedBooking($bookingId);

        if ($booking === null) {
            return $this->redirect(['action' => 'index']);
        }

        /*
         * Only a booking made on this site can be moved here: Square needs
         * the treatment and its duration to search for a free slot, and a
         * booking Wendy took over the phone has no draft holding them.
         */
        $draft = $booking['draft'];

        if ($draft === null || empty($draft->segments)) {
            $this->Flash->error(
                'That appointment was made over the phone, so please call the studio to change it.',
            );

            return $this->redirect(['action' => 'index']);
        }

        $api = new SquareApiService();

        try {
            $location = $api->getPrimaryLocation();
            $zone = new DateTimeZone($location['timezone']);
            [$earliest, $latest] = $this->calendarBounds($zone);
            $from = $this->weekFrom($this->request->getQuery('from'), $zone, $earliest, $latest);

            $slots = $api->searchAvailability(
                $this->BookingDraftsTable()->variationIds($draft),
                $from,
                $from->modify('+' . $this->windowDays() . ' days'),
            );

            /*
             * An update may only carry the time, so whoever is doing the
             * treatment cannot change with it. Offering a slot that belongs
             * to someone else would look free and then be refused, so those
             * are dropped before the customer ever sees them.
             */
            $slots = $this->slotsServedBy($slots, $api->bookingTeamMemberIds((string)$bookingId));
        } catch (Throwable $e) {
            Log::error('Reschedule availability failed: ' . $e->getMessage());

            $this->Flash->error('We could not reach the booking calendar just now. Please try again shortly.');

            return $this->redirect(['action' => 'index']);
        }

        if ($this->request->is('post')) {
            $chosen = (string)$this->request->getData('start_at');
            $match = null;

            // Matched against what Square just returned, so the browser
            // cannot name a time that is not really free.
            foreach ($slots as $slot) {
                if ($slot['start_at'] === $chosen) {
                    $match = $slot;

                    break;
                }
            }

            if ($match === null) {
                $this->Flash->error('That time has just been taken. Please choose another.');
            } else {
                try {
                    $api->rescheduleBooking((string)$bookingId, $match['start_at']);
                } catch (Throwable $e) {
                    Log::error('Reschedule failed: ' . $e->getMessage());

                    $this->Flash->error(
                        'We could not move that appointment - the time may have just been taken. '
                        . 'Please choose another.',
                    );

                    return $this->redirect(['action' => 'reschedule', $bookingId]);
                }

                $draft->start_at = new DateTime($match['start_at']);
                $draft->segments = $match['segments'];
                $this->BookingDraftsTable()->save($draft);

                $this->updateLocalStart($bookingId, $match['start_at']);

                $this->Flash->success('That appointment has been moved.');

                return $this->redirect(['action' => 'index']);
            }
        }

        $byDay = $this->groupByDay($slots, $zone);

        $this->set('booking', $booking);
        $this->set('bookingId', $bookingId);
        $this->set('timezone', $location['timezone']);
        $this->set(compact('byDay'));
        $this->setCalendar($byDay, $from, $earliest, $latest);
    }

    /**
     * Keeps only the slots the given team members could take.
     *
     * @param array<int, array<string, mixed>> $slots
     * @param array<int, string> $teamMemberIds Empty means no restriction.
     * @return array<int, array<string, mixed>>
     */
    protected function slotsServedBy(array $slots, array $teamMemberIds): array
    {
        if ($teamMemberIds === []) {
            return $slots;
        }

        $kept = [];

        foreach ($slots as $slot) {
            $members = array_column($slot['segments'], 'team_member_id');

            // Every segment has to be one of theirs: a slot half-served by
            // someone else is not a slot this booking can move into.
            if (array_diff($members, $teamMemberIds) === []) {
                $kept[] = $slot;
            }
        }

        return $kept;
    }

    /**
     * Issues a code and sends the customer to type it in.
     *
     * @param string $phone
     * @return \Cake\Http\Response
     */
    protected function sendCode(string $phone): Response
    {
        $issued = $this->BookingAccessCodes->issue($phone);
        $session = $this->request->getSession();
        $session->write('MyBookings.pending', $phone);

        if ($issued['code'] === null) {
            $this->Flash->error(sprintf(
                'A code was just sent. Please wait %d seconds before asking for another.',
                $issued['wait'],
            ));

            return $this->redirect(['action' => 'verify']);
        }

        $shown = (new BookingNotifier())->sendAccessCode($phone, $issued['code']);

        // Null in production: there the code only exists in the text message.
        $session->write('MyBookings.shownCode', $shown);

        $this->Flash->success('We have sent a code to that number.');

        return $this->redirect(['action' => 'verify']);
    }

    /**
     * The verified number, if the proof is still good.
     *
     * @return string|null
     */
    protected function verifiedPhone(): ?string
    {
        $session = $this->request->getSession();
        $phone = $session->read(self::SESSION_KEY);
        $expires = (int)$session->read(self::SESSION_EXPIRY_KEY);

        if ($phone === null) {
            return null;
        }

        if ($expires < time()) {
            $session->delete('MyBookings');

            return null;
        }

        return (string)$phone;
    }

    /**
     * One booking from this customer's own list, or null with a message.
     *
     * Looked up rather than trusted: the id is in the URL, and reading it
     * back out of the verified number's bookings is what stops one customer
     * cancelling another's appointment.
     *
     * @param string|null $bookingId
     * @return array<string, mixed>|null
     */
    protected function ownedBooking(?string $bookingId): ?array
    {
        $phone = $this->verifiedPhone();

        if ($phone === null) {
            $this->Flash->error('Please enter your number again to see your bookings.');

            return null;
        }

        foreach ((new CustomerBookingLookup())->upcoming($phone) as $booking) {
            if ($booking['square_booking_id'] === $bookingId) {
                return $booking;
            }
        }

        $this->Flash->error('That appointment could not be found.');

        return null;
    }

    /**
     * Reflects a cancellation locally so the list is right immediately.
     *
     * Square is the record; this only saves the customer seeing a booking
     * they just cancelled sitting there until the next sync runs.
     *
     * @param string|null $bookingId
     * @return void
     */
    protected function markCancelledLocally(?string $bookingId): void
    {
        // Square's own wording, lowercased the way the sync writes it - the
        // column is an enum of exactly those values.
        $this->fetchTable('Bookings')->updateAll(
            ['status' => 'cancelled_by_customer'],
            ['square_booking_id' => $bookingId],
        );

        $this->BookingDraftsTable()->updateAll(
            ['status' => 'cancelled'],
            ['square_booking_id' => $bookingId],
        );
    }

    /**
     * @param string|null $bookingId
     * @param string $startAt RFC 3339, UTC.
     * @return void
     */
    protected function updateLocalStart(?string $bookingId, string $startAt): void
    {
        /*
         * Parsed rather than constructed, so the value lands in the server's
         * timezone - the same way the sync writes this column. Constructing
         * it would keep Square's UTC and put the row an hour or ten out from
         * every other row in the table.
         */
        $this->fetchTable('Bookings')->updateAll(
            ['start_at' => DateTime::parse($startAt)],
            ['square_booking_id' => $bookingId],
        );
    }

    /**
     * @return \Cake\ORM\Table
     */
    protected function BookingDraftsTable(): Table
    {
        return $this->fetchTable('BookingDrafts');
    }

    /**
     * The studio's own timezone, so times read the way they are spoken.
     *
     * @return string
     */
    protected function timezone(): string
    {
        try {
            return (new SquareApiService())->getPrimaryLocation()['timezone'];
        } catch (Throwable $e) {
            // A sensible default beats no time at all on someone's booking.
            return 'Australia/Adelaide';
        }
    }
}
