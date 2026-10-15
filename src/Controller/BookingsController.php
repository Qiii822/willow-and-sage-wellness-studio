<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Booking;
use App\Model\Table\BookingsTable;
use Cake\Http\Response;
use Cake\I18n\DateTime;

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
