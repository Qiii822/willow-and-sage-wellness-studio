<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\SquareApiService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The month-at-a-time day picker, shared by the two pages that offer times.
 *
 * Choosing a slot when booking and choosing one when moving a booking are
 * the same question asked twice, so they ask it the same way rather than
 * drifting apart: one Square call covering four weeks, shown a week at a
 * time, with the arrows and the date box working inside it.
 */
trait BookingCalendarTrait
{
    /** Days shown at once - a week, like a wall calendar. */
    protected const WEEK_DAYS = 7;

    /**
     * How much is fetched in one go: four weeks, shown a week at a time.
     *
     * Square refuses a query range over 32 days, so this is the largest
     * round number of weeks that fits. Asking for the month up front means
     * the arrows move between weeks without another request - only stepping
     * outside the fetched month costs a page load.
     */
    protected const WINDOW_WEEKS = 4;

    /**
     * How many days each availability search covers - Square's maximum.
     *
     * Not the four weeks on show, and not a rounding detail: Square does not
     * return every free start, and which ones it leaves out depends on how
     * long the searched range is. Its own booking page searches 32 days, so
     * searching anything shorter offered a different set of times for the
     * same day - checked against the studio's page, a 28-day search showed
     * 26 times where Square showed 30, and 32 days matched it exactly. The
     * days past the four weeks are fetched only to keep that set the same,
     * and are dropped before the page sees them.
     */
    protected const SEARCH_DAYS = 32;

    /**
     * How far ahead Square will take a booking on this account.
     *
     * It checks the far end of the search window, not the near one, so the
     * last month on offer has to finish inside this rather than start
     * inside it - otherwise asking for it is a 400 and the customer lands
     * on "we could not reach the booking calendar", which is not what
     * happened.
     */
    protected const BOOKING_HORIZON_DAYS = 365;

    /**
     * Days fetched in one go.
     *
     * @return int
     */
    protected function windowDays(): int
    {
        return self::WINDOW_WEEKS * self::WEEK_DAYS;
    }

    /**
     * Searches Square for the window starting on $from, the way its own
     * booking page does, and keeps only what falls inside the window.
     *
     * @param \App\Service\SquareApiService $api
     * @param array<int, string> $variationIds
     * @param \DateTimeImmutable $from First day of the window.
     * @param string|null $teamMemberId Null means anyone available.
     * @return array<int, array<string, mixed>>
     */
    protected function searchWindow(
        SquareApiService $api,
        array $variationIds,
        DateTimeImmutable $from,
        ?string $teamMemberId = null,
    ): array {
        $slots = $api->searchAvailability(
            $variationIds,
            $from,
            $from->modify('+' . self::SEARCH_DAYS . ' days'),
            $teamMemberId,
        );

        $end = $from->modify('+' . $this->windowDays() . ' days');

        return array_values(array_filter(
            $slots,
            fn(array $slot): bool => new DateTimeImmutable($slot['start_at']) < $end,
        ));
    }

    /**
     * The soonest and the furthest first-day a window may start on.
     *
     * @param \DateTimeZone $zone
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    protected function calendarBounds(DateTimeZone $zone): array
    {
        $today = new DateTimeImmutable('today', $zone);

        return [
            $today->modify('+1 day'),
            // Room for the whole search, not just the weeks on show, plus a
            // day of slack so its end stays inside Square's limit.
            $today->modify(
                '+' . (self::BOOKING_HORIZON_DAYS - self::SEARCH_DAYS - 1) . ' days',
            ),
        ];
    }

    /**
     * Reads the requested first day, or falls back to the soonest.
     *
     * Anything unparseable or outside what Square will take a booking for
     * is ignored rather than refused: a mistyped date in the address bar
     * should land on the soonest day, not on an error page.
     *
     * @param mixed $requested
     * @param \DateTimeZone $zone
     * @param \DateTimeImmutable $earliest
     * @param \DateTimeImmutable $latest
     * @return \DateTimeImmutable
     */
    protected function weekFrom(
        mixed $requested,
        DateTimeZone $zone,
        DateTimeImmutable $earliest,
        DateTimeImmutable $latest,
    ): DateTimeImmutable {
        if (!is_string($requested) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested)) {
            return $earliest;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $requested . ' 00:00:00', $zone);

        if ($parsed === false || $parsed->format('Y-m-d') !== $requested) {
            return $earliest;
        }

        if ($parsed < $earliest) {
            return $earliest;
        }

        return $parsed > $latest ? $latest : $parsed;
    }

    /**
     * Slots keyed by the local day they fall on.
     *
     * @param array<int, array<string, mixed>> $slots
     * @param \DateTimeZone $zone
     * @return array<string, array<int, array{start_at: string, label: string}>>
     */
    protected function groupByDay(array $slots, DateTimeZone $zone): array
    {
        $byDay = [];

        foreach ($slots as $slot) {
            $local = (new DateTimeImmutable($slot['start_at']))->setTimezone($zone);

            $byDay[$local->format('Y-m-d')][] = [
                'start_at' => $slot['start_at'],
                'label' => $local->format('g:i a'),
            ];
        }

        return $byDay;
    }

    /**
     * "8 - 14 September", or across a month boundary "29 Sep - 5 Oct".
     *
     * @param \DateTimeImmutable $from
     * @param \DateTimeImmutable $to
     * @return string
     */
    protected function rangeLabel(DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        if ($from->format('F') === $to->format('F')) {
            return $from->format('j') . ' - ' . $to->format('j F');
        }

        return $from->format('j M') . ' - ' . $to->format('j M');
    }

    /**
     * The fetched month, split into weeks of seven days.
     *
     * Closed days are listed too, greyed out: a calendar with them missing
     * reads as broken, while showing them answers "are you open Sunday?"
     * without the customer having to ask.
     *
     * Always the full four weeks: the window only ever starts on a day that
     * leaves room for all of them inside what Square will book, so there is
     * no short last month to trim.
     *
     * @param array<string, array<int, array<string, mixed>>> $byDay
     * @param \DateTimeImmutable $from First day of the window.
     * @return array<int, array{start: string, label: string, slots: int, days: array<int, array{date: string, day: string, weekday: string, slots: int}>}>
     */
    protected function weeksFor(array $byDay, DateTimeImmutable $from): array
    {
        $weeks = [];
        $cursor = $from;
        $zone = $from->getTimezone();

        for ($w = 0; $w < self::WINDOW_WEEKS; $w++) {
            $days = [];
            $total = 0;

            for ($d = 0; $d < self::WEEK_DAYS; $d++) {
                $key = $cursor->format('Y-m-d');
                $count = count($byDay[$key] ?? []);
                $total += $count;

                $days[] = [
                    'date' => $key,
                    'day' => $cursor->format('j'),
                    'weekday' => $cursor->format('D'),
                    'slots' => $count,
                ];

                $cursor = $cursor->modify('+1 day');
            }

            $weeks[] = [
                'start' => $days[0]['date'],
                'label' => $this->rangeLabel(
                    new DateTimeImmutable($days[0]['date'], $zone),
                    new DateTimeImmutable($days[count($days) - 1]['date'], $zone),
                ),
                'slots' => $total,
                'days' => $days,
            ];
        }

        return $weeks;
    }

    /**
     * Hands the view everything the picker needs to draw itself.
     *
     * @param array<string, array<int, array<string, mixed>>> $byDay
     * @param \DateTimeImmutable $from First day of the window.
     * @param \DateTimeImmutable $earliest
     * @param \DateTimeImmutable $latest
     * @return void
     */
    protected function setCalendar(
        array $byDay,
        DateTimeImmutable $from,
        DateTimeImmutable $earliest,
        DateTimeImmutable $latest,
    ): void {
        $weeks = $this->weeksFor($byDay, $from);
        $zone = $from->getTimezone();
        $windowTo = new DateTimeImmutable(
            $weeks[count($weeks) - 1]['days'][self::WEEK_DAYS - 1]['date'],
            $zone,
        );
        $after = $from->modify('+' . $this->windowDays() . ' days');

        $this->set('weeks', $weeks);
        $this->set('window', [
            'from' => $from->format('Y-m-d'),
            'to' => $windowTo->format('Y-m-d'),
            'earliest' => $earliest->format('Y-m-d'),
            'latest' => $latest->format('Y-m-d'),
            // Names the whole fetched span, which is what the heading says
            // until the script narrows it to the week actually on show.
            'label' => $this->rangeLabel($from, $windowTo),
            /*
             * Where the arrows point when they run off the end of what was
             * fetched. Null where there is nowhere left to go, so the arrow
             * can be switched off rather than aim outside what Square takes.
             */
            'before' => $from > $earliest
                ? max($earliest, $from->modify('-' . self::WEEK_DAYS . ' days'))->format('Y-m-d')
                : null,
            'after' => $after <= $latest ? $after->format('Y-m-d') : null,
        ]);
    }
}
