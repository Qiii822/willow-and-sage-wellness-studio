<?php
/**
 * One day's times, under Morning, Afternoon and Evening as Square's own
 * booking page lays them out - including "No availability" under a part of
 * the day with nothing in it, so an empty evening reads as closed rather
 * than as missing.
 *
 * Grouped here by the studio's clock, which is what a browser without
 * scripting keeps. local_times.js rewrites each time on the visitor's own
 * clock and then moves it under the heading that clock puts it in: a 11:45
 * slot in Adelaide is 12:15 in Melbourne, and belongs under Afternoon there.
 *
 * @var \App\View\AppView $this
 * @var array<int, array{start_at: string, label: string}> $slots
 * @var string $timezone The studio's zone.
 */

$zone = new DateTimeZone($timezone);

/*
 * Where each part of the day starts, by hour. The same split as Square's
 * page, and repeated in local_times.js, which re-sorts on the visitor's clock.
 */
$periods = [
    'morning' => ['label' => 'Morning', 'from' => 0],
    'afternoon' => ['label' => 'Afternoon', 'from' => 12],
    'evening' => ['label' => 'Evening', 'from' => 17],
];

$grouped = array_fill_keys(array_keys($periods), []);

foreach ($slots as $slot) {
    $hour = (int)(new DateTimeImmutable($slot['start_at']))->setTimezone($zone)->format('G');
    $period = 'morning';

    foreach ($periods as $key => $info) {
        if ($hour >= $info['from']) {
            $period = $key;
        }
    }

    $grouped[$period][] = $slot;
}
?>

<div class="book-periods" data-periods>

    <?php foreach ($periods as $key => $info): ?>

        <div class="book-period" data-period="<?= h($key) ?>" data-period-from="<?= $info['from'] ?>">

            <h3><?= h($info['label']) ?></h3>

            <div class="book-slots" data-period-slots>

                <?php foreach ($grouped[$key] as $slot): ?>

                    <?php /*
                     * A button per slot rather than radios plus a
                     * submit: choosing the time is the decision, so
                     * it should also be the action.
                     */ ?>
                    <button
                        type="submit"
                        name="start_at"
                        value="<?= h($slot['start_at']) ?>"
                        class="book-slot"
                        data-local-time="<?= h($slot['start_at']) ?>"
                    ><?= h($slot['label']) ?></button>

                <?php endforeach; ?>

            </div>

            <p class="book-period-empty" data-period-empty<?= $grouped[$key] === [] ? '' : ' hidden' ?>>
                No availability
            </p>

        </div>

    <?php endforeach; ?>

</div>
