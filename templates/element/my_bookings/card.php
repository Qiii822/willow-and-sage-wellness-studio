<?php
/**
 * One appointment on My bookings, as a card: a date badge, what it is,
 * when and with whom, what it costs, and what can be done about it.
 *
 * Every date and time is printed in the studio's zone and rewritten on the
 * visitor's clock by local_times.js, the badge included.
 *
 * @var \App\View\AppView $this
 * @var array<string, mixed> $booking A row from CustomerBookingLookup.
 * @var \DateTimeZone $zone The studio's zone.
 * @var array<int, string> $actions Buttons and links, already rendered.
 * @var bool $isPast Whether it has been, rather than is coming up. Not
 *  "$past": the page's list of past bookings goes by that name, and an
 *  element sees the page's variables too.
 */

$isPast = !empty($isPast);
$actions = $actions ?? [];

$start = $booking['start_at']->setTimezone($zone);
$end = $booking['end_at'] !== null ? $booking['end_at']->setTimezone($zone) : null;
$at = h($start->format(DATE_RFC3339));

$details = [];

if ($booking['minutes'] !== null) {
    $details[] = h($this->Book->duration($booking['minutes']));
}

if ($booking['therapist'] !== null) {
    $details[] = 'with ' . h($booking['therapist']);
}
?>

<article class="mybooking-card<?= $isPast ? ' is-past' : '' ?><?= $booking['status_label'] !== null ? ' is-off' : '' ?>">

    <?php /* The date in full is in the text beside it; this is for the eye. */ ?>
    <div class="mybooking-badge" aria-hidden="true">
        <span data-local-time="<?= $at ?>" data-local-format="weekday"><?= h($start->format('D')) ?></span>
        <span class="mybooking-badge-day" data-local-time="<?= $at ?>" data-local-format="day"><?= h($start->format('j')) ?></span>
        <span data-local-time="<?= $at ?>" data-local-format="month"><?= h($start->format('M')) ?></span>
    </div>

    <div class="mybooking-body">

        <?php if ($booking['status_label'] !== null): ?>
            <p class="mybooking-status"><?= h($booking['status_label']) ?></p>
        <?php endif; ?>

        <h3 class="mybooking-title"><?= h(implode(' + ', $booking['treatments'])) ?></h3>

        <p class="mybooking-when">
            <span
                data-local-time="<?= $at ?>"
                data-local-format="<?= $isPast ? 'shortdate' : 'date' ?>"
            ><?= h($start->format($isPast ? 'D j M Y' : 'l j F')) ?></span>
            &middot;
            <span data-local-time="<?= $at ?>"><?= h($start->format('g:i a')) ?></span><?php if ($end !== null): ?>
                &ndash;
                <span data-local-time="<?= h($end->format(DATE_RFC3339)) ?>"><?= h($end->format('g:i a')) ?></span>
            <?php endif; ?>
        </p>

        <?php if ($details !== []): ?>
            <p class="mybooking-details"><?= implode(' &middot; ', $details) ?></p>
        <?php endif; ?>

    </div>

    <div class="mybooking-side">

        <?php if ($booking['price_cents'] !== null): ?>
            <p class="mybooking-price">AU$<?= h(number_format($booking['price_cents'] / 100, 2)) ?></p>
        <?php endif; ?>

        <?php if ($actions !== []): ?>
            <div class="mybooking-actions">
                <?= implode('', $actions) ?>
            </div>
        <?php endif; ?>

    </div>

</article>
