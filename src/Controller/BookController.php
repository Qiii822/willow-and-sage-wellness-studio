<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\SquareApiService;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Book Controller
 *
 * The customer-facing booking flow: choose treatments, choose a time,
 * enter details. Public throughout - there are no accounts on this site,
 * and a draft is reached by an unguessable token rather than a login.
 *
 * @property \App\Model\Table\BookingDraftsTable $BookingDrafts
 */
class BookController extends AppController
{
    use BookingCalendarTrait;

    /**
     * How far past an empty month to look for the next free day.
     *
     * Kept under Square's 32-day query limit, the same one that caps the
     * window above - a wider look would be refused outright and the hint
     * would never appear.
     */
    protected const LOOKAHEAD_DAYS = 30;

    /**
     * Loaded by hand: the controller is named for the flow, not for a
     * table, so nothing is picked up automatically from the name.
     */
    protected \Cake\ORM\Table $BookingDrafts;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->BookingDrafts = $this->fetchTable('BookingDrafts');
    }

    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['index', 'staff', 'time', 'details', 'done']);

        $this->viewBuilder()->setLayout('site');
    }

    /**
     * Choose the treatments.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index()
    {
        $services = $this->fetchTable('Services');

        /*
         * Carried to the view rather than flashed. What went wrong is about
         * the cart - it is empty, or nothing in it can be booked - so it
         * belongs beside the button that was pressed, not in a bar at the
         * top of the page above the studio's name.
         */
        $cartError = null;

        if ($this->request->is('post')) {
            $chosen = array_filter((array)$this->request->getData('service_ids'));

            if ($chosen === []) {
                $cartError = 'Add at least one treatment to continue.';
            } else {
                $selected = $services->find()
                    ->where(['id IN' => $chosen, 'visible' => true])
                    ->all()
                    ->toList();

                /*
                 * A treatment with no Square variation cannot be booked -
                 * availability and the booking itself are both keyed on it.
                 * Better to say so here than to offer times for something
                 * that will be refused at the end.
                 */
                $bookable = array_filter(
                    $selected,
                    fn ($service): bool => !empty($service->square_variation_id),
                );

                if ($bookable === []) {
                    $cartError = 'Those treatments cannot be booked online yet. Please call the studio.';
                } else {
                    $draft = $this->BookingDrafts->start(array_values($bookable));

                    return $this->redirect(['action' => 'staff', $draft->token]);
                }
            }
        }

        $this->set('menu', $services->menu());
        $this->set(compact('cartError'));
    }

    /**
     * Choose who does the treatment.
     *
     * @param string|null $token
     * @return \Cake\Http\Response|null|void
     */
    public function staff(?string $token = null)
    {
        $draft = $this->BookingDrafts->findByToken($token);

        if (!$draft) {
            return $this->notFoundRedirect();
        }

        try {
            $team = (new SquareApiService())->listBookableTeamMembers();
        } catch (Throwable $e) {
            return $this->unavailable($e);
        }

        if ($this->request->is('post')) {
            $chosen = (string)$this->request->getData('team_member_id');

            /*
             * Checked against Square's own list rather than trusted: the
             * form could name anyone, and a team member who does not take
             * this treatment would only fail later, at the availability
             * search, with nothing useful to say.
             */
            $valid = $chosen === '' || in_array($chosen, array_column($team, 'id'), true);

            if (!$valid) {
                $this->Flash->error('Please choose from the therapists listed.');
            } else {
                // Empty means no preference, which is stored as null rather
                // than as an empty string so the filter can simply be absent.
                $draft->team_member_id = $chosen === '' ? null : $chosen;
                $this->BookingDrafts->saveOrFail($draft);

                return $this->redirect(['action' => 'time', $draft->token]);
            }
        }

        $this->set(compact('draft', 'team'));
    }

    /**
     * Choose a time.
     *
     * @param string|null $token
     * @return \Cake\Http\Response|null|void
     */
    public function time(?string $token = null)
    {
        $draft = $this->BookingDrafts->findByToken($token);

        if (!$draft) {
            return $this->notFoundRedirect();
        }

        $api = new SquareApiService();

        try {
            $location = $api->getPrimaryLocation();
        } catch (Throwable $e) {
            return $this->unavailable($e);
        }

        $zone = new DateTimeZone($location['timezone']);
        [$earliest, $latest] = $this->calendarBounds($zone);
        $from = $this->weekFrom($this->request->getQuery('from'), $zone, $earliest, $latest);

        if ($this->request->is('post')) {
            $chosen = (string)$this->request->getData('start_at');

            // Re-fetched rather than trusted: the browser could name any
            // time, and only Square can say whether it is really free.
            $slots = $this->slotsFor($api, $draft, $from);
            $match = null;

            foreach ($slots as $slot) {
                if ($slot['start_at'] === $chosen) {
                    $match = $slot;

                    break;
                }
            }

            if ($match === null) {
                $this->Flash->error('That time has just been taken. Please choose another.');
            } else {
                $draft->start_at = new \Cake\I18n\DateTime($match['start_at']);
                $draft->segments = $match['segments'];
                $draft->status = 'details';

                $this->BookingDrafts->saveOrFail($draft);

                return $this->redirect(['action' => 'details', $draft->token]);
            }
        }

        try {
            $slots = $this->slotsFor($api, $draft, $from);
        } catch (Throwable $e) {
            return $this->unavailable($e);
        }

        // Grouped by day, so the page can offer days rather than a list of
        // eighty timestamps.
        $byDay = $this->groupByDay($slots, $zone);

        $therapist = null;

        if ($draft->team_member_id !== null) {
            foreach ($api->listBookableTeamMembers() as $member) {
                if ($member['id'] === $draft->team_member_id) {
                    $therapist = $member['name'];

                    break;
                }
            }
        }

        $this->set(compact('draft', 'byDay', 'location', 'therapist'));
        $this->set('timezone', $location['timezone']);
        $this->setCalendar($byDay, $from, $earliest, $latest);

        /*
         * A month with nothing in it is a dead end unless the page can say
         * where to go next, so an empty one is followed by a wider look
         * ahead. Only then - the common case costs one call, not two.
         */
        $this->set('nextAvailable', $byDay === []
            ? $this->nextAvailableAfter($api, $draft, $from, $latest, $zone)
            : null);
    }

    /**
     * The first bookable day after the week on show, if there is one.
     *
     * @param \App\Service\SquareApiService $api
     * @param \App\Model\Entity\BookingDraft $draft
     * @param \DateTimeImmutable $from
     * @param \DateTimeImmutable $latest
     * @param \DateTimeZone $zone
     * @return array{date: string, label: string}|null
     */
    protected function nextAvailableAfter(
        SquareApiService $api,
        $draft,
        DateTimeImmutable $from,
        DateTimeImmutable $latest,
        DateTimeZone $zone,
    ): ?array {
        $start = $from->modify('+' . $this->windowDays() . ' days');

        if ($start > $latest) {
            return null;
        }

        try {
            $ahead = $api->searchAvailability(
                $this->BookingDrafts->variationIds($draft),
                $start,
                min($latest, $start->modify('+' . self::LOOKAHEAD_DAYS . ' days')),
                $draft->team_member_id,
            );
        } catch (Throwable $e) {
            // A failed hint is not worth failing the page over; the arrows
            // are still there to look with.
            return null;
        }

        if ($ahead === []) {
            return null;
        }

        $local = (new DateTimeImmutable($ahead[0]['start_at']))->setTimezone($zone);

        return [
            'date' => $local->format('Y-m-d'),
            'label' => $local->format('l j F'),
        ];
    }

    /**
     * Enter contact details and a discount code.
     *
     * @param string|null $token
     * @return \Cake\Http\Response|null|void
     */
    public function details(?string $token = null)
    {
        $draft = $this->BookingDrafts->findByToken($token);

        if (!$draft || $draft->start_at === null) {
            return $this->notFoundRedirect();
        }

        if ($draft->status === 'confirmed') {
            return $this->redirect(['action' => 'done', $draft->token]);
        }

        if ($this->request->is(['post', 'put'])) {
            $draft = $this->BookingDrafts->patchEntity($draft, $this->request->getData());

            if ($this->BookingDrafts->save($draft)) {
                $confirmed = $this->confirm($draft);

                if ($confirmed) {
                    return $this->redirect(['action' => 'done', $draft->token]);
                }
            } else {
                $this->Flash->error('Please check the details below.');
            }
        }

        $timezone = 'Australia/Adelaide';

        try {
            $timezone = (new SquareApiService())->getPrimaryLocation()['timezone'];
        } catch (Throwable $e) {
            // Showing the time in a sensible default beats showing none.
        }

        $this->set(compact('draft', 'timezone'));
    }

    /**
     * The booking is made; show it.
     *
     * @param string|null $token
     * @return \Cake\Http\Response|null|void
     */
    public function done(?string $token = null)
    {
        $draft = $this->BookingDrafts->findByToken($token);

        if (!$draft || $draft->status !== 'confirmed') {
            return $this->notFoundRedirect();
        }

        $timezone = 'Australia/Adelaide';

        try {
            $timezone = (new SquareApiService())->getPrimaryLocation()['timezone'];
        } catch (Throwable $e) {
            // A sensible default beats no time at all on a confirmation.
        }

        $this->set(compact('draft', 'timezone'));
    }

    /**
     * What to tell the customer when Square refuses the booking.
     *
     * This used to say "that time may have just been taken" whatever went
     * wrong, which sent people hunting for a clash that was not there - a
     * mistyped phone number is rejected while Square is creating the
     * customer, long before the calendar is involved, and the error names
     * the field. So the ones that name a field are answered honestly, and
     * only the rest fall back to the clash, which is what the rest usually
     * are.
     *
     * @param string $error Square's own message.
     * @return string
     */
    protected function confirmationError(string $error): string
    {
        if (str_contains($error, 'INVALID_PHONE_NUMBER')) {
            return 'That phone number was not accepted. Please check it and try again.';
        }

        if (str_contains($error, 'INVALID_EMAIL_ADDRESS')) {
            return 'That email address was not accepted. Please check it and try again.';
        }

        return 'We could not confirm that time - it may have just been taken. '
            . 'Please choose another.';
    }

    /**
     * Writes the draft into Square.
     *
     * The customer is created first: without one Square will not take the
     * booking, and a customer left behind by a failed booking is harmless -
     * a duplicate booking would not be.
     *
     * @param \App\Model\Entity\BookingDraft $draft
     * @return bool Whether it went through.
     */
    protected function confirm($draft): bool
    {
        $api = new SquareApiService();

        try {
            $customerId = $api->findOrCreateCustomer(
                (string)$draft->customer_name,
                (string)$draft->customer_email,
                (string)$draft->customer_phone,
            );

            $bookingId = $api->createBooking(
                $customerId,
                // Square wants the instant, not the wall clock we display.
                (string)$draft->startAtUtc()?->format(DATE_RFC3339),
                $draft->segments,
                $draft->customer_note,
            );
        } catch (Throwable $e) {
            \Cake\Log\Log::error('Booking confirmation failed: ' . $e->getMessage());

            $draft->status = 'failed';
            $draft->failure_reason = substr($e->getMessage(), 0, 500);
            $this->BookingDrafts->save($draft);

            $this->Flash->error($this->confirmationError($e->getMessage()));

            return false;
        }

        $draft->status = 'confirmed';
        $draft->square_customer_id = $customerId;
        $draft->square_booking_id = $bookingId;
        $draft->failure_reason = null;

        $this->BookingDrafts->saveOrFail($draft);

        return true;
    }

    /**
     * Slots for everything in the draft, across the fetched month.
     *
     * One call covers four weeks, so the arrows can move between them
     * without going back to Square. Square refuses a range over 32 days,
     * which is what stops this being wider still.
     *
     * @param \App\Service\SquareApiService $api
     * @param \App\Model\Entity\BookingDraft $draft
     * @param \DateTimeImmutable $from First day of the window.
     * @return array<int, array<string, mixed>>
     */
    protected function slotsFor(SquareApiService $api, $draft, DateTimeImmutable $from): array
    {
        return $api->searchAvailability(
            $this->BookingDrafts->variationIds($draft),
            $from,
            $from->modify('+' . $this->windowDays() . ' days'),
            $draft->team_member_id,
        );
    }

    /**
     * @return \Cake\Http\Response
     */
    protected function notFoundRedirect(): Response
    {
        $this->Flash->error('That booking could not be found. Please start again.');

        return $this->redirect(['action' => 'index']);
    }

    /**
     * @param \Throwable $e
     * @return \Cake\Http\Response
     */
    protected function unavailable(Throwable $e): Response
    {
        \Cake\Log\Log::error('Booking availability failed: ' . $e->getMessage());

        $this->Flash->error(
            'We could not reach the booking calendar just now. Please try again shortly.',
        );

        return $this->redirect(['controller' => 'Services', 'action' => 'index']);
    }
}
