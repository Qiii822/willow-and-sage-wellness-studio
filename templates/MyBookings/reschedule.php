<?php
/**
 * Pick a new time for an existing appointment.
 *
 * The same picker as the booking flow, and for the same reason: choosing a
 * slot is one question, so it should not be asked two different ways
 * depending on whether the customer is booking or moving a booking.
 *
 * @var \App\View\AppView $this
 * @var array<string, mixed> $booking
 * @var string $bookingId
 * @var array<string, array<int, array{start_at: string, label: string}>> $byDay
 * @var array<int, array{start: string, label: string, slots: int, days: array<int, array{date: string, day: string, weekday: string, slots: int}>}> $weeks
 * @var array{from: string, to: string, earliest: string, latest: string, label: string, before: string|null, after: string|null} $window
 * @var string $timezone
 */

$this->assign('title', 'Change your time - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);
$this->Html->script('book_calendar', ['block' => true]);
$this->Html->script('local_times', ['block' => true]);

$here = ['action' => 'reschedule', $bookingId];

// Converted, not relabelled: the stored value carries the server's own
// timezone rather than the studio's.
$current = $booking['start_at']->setTimezone(new DateTimeZone($timezone));

// The first day of the month with anything on it. The script opens the
// picker on its week; the date box starts there whether or not it runs.
$openDay = null;

foreach ($weeks as $week) {
    foreach ($week['days'] as $day) {
        if ($day['slots'] > 0) {
            $openDay = $day['date'];

            break 2;
        }
    }
}
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page">

        <header class="book-header">
            <h1>Change your time</h1>
            <p>
                <?= h(implode(', ', $booking['treatments'])) ?>, currently
                <strong
                    data-local-time="<?= h($current->format(DATE_RFC3339)) ?>"
                    data-local-format="long"
                ><?= h($current->format('l j F, g:i a')) ?></strong>.
                Choose a new time below and we will move it.
                <span data-zone-note="<?= h($timezone) ?>"></span>
            </p>
        </header>

        <?= $this->element('book/calendar', [
            'weeks' => $weeks,
            'window' => $window,
            'url' => $here,
            'openDay' => $openDay,
        ]) ?>

        <?php if ($byDay === []): ?>

            <p class="book-empty">
                Nothing free in these four weeks. Try another month with the
                arrows above, or call the studio and we will find something.
            </p>

        <?php else: ?>

            <?php /*
             * Posts back to the month on show, so the slot is checked
             * against the same set of times the customer was looking at.
             */ ?>
            <?= $this->Form->create(null, [
                'class' => 'book-form',
                'url' => $here + ['?' => ['from' => $window['from']]],
            ]) ?>

            <?php foreach ($byDay as $day => $slots): ?>

                <?php $date = new DateTimeImmutable($day); ?>

                <section class="book-day" data-day="<?= h($day) ?>">

                    <h2><?= h($date->format('l j F')) ?></h2>

                    <?= $this->element('book/day_slots', [
                        'slots' => $slots,
                        'timezone' => $timezone,
                    ]) ?>

                </section>

            <?php endforeach; ?>

            <?= $this->Form->end() ?>

        <?php endif; ?>

        <p class="book-back">
            <?= $this->Html->link('Keep it as it is', ['action' => 'index']) ?>
        </p>

    </section>

</main>
