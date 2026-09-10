<?php
/**
 * Step 3: choose a time.
 *
 * A week at a time, then a day, then a time within it. Listing a month of
 * slots one after another - which is what this page used to do - turns a
 * simple choice into a scroll, and buries the fact that some days have
 * nothing at all.
 *
 * Four weeks are fetched and rendered, and only one is on show. So both the
 * week arrows and the day cells are instant: nothing goes back to Square
 * until the customer steps outside the month that was fetched, and then it
 * is an ordinary link that loads the next one.
 *
 * Without scripting the day cells and the week rows stay hidden and every
 * day is listed one after another, with the arrows as plain links. That
 * still works, it is just slower.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\BookingDraft $draft
 * @var array<string, array<int, array{start_at: string, label: string}>> $byDay
 * @var array<int, array{start: string, label: string, slots: int, days: array<int, array{date: string, day: string, weekday: string, slots: int}>}> $weeks
 * @var array{from: string, to: string, earliest: string, latest: string, before: string|null, after: string|null} $window
 * @var array{date: string, label: string}|null $nextAvailable
 * @var array{id: string, name: string, timezone: string} $location
 * @var string|null $therapist
 * @var string $timezone
 */

$this->assign('title', 'Choose a time - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);
$this->Html->script('book_calendar', ['block' => true]);

$here = ['action' => 'time', $draft->token];

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

        <?= $this->element('book/steps', ['current' => 3]) ?>

        <header class="book-header">
            <h1>Choose a time</h1>
            <p>
                <?php if ($therapist !== null): ?>
                    With <strong><?= h($therapist) ?></strong>.
                <?php endif; ?>
                All times are <?= h(str_replace('_', ' ', explode('/', $timezone)[1] ?? $timezone)) ?>
                time, where the studio is.
            </p>
        </header>

        <?= $this->element('book/summary', ['draft' => $draft]) ?>

        <?= $this->element('book/calendar', [
            'weeks' => $weeks,
            'window' => $window,
            'url' => $here,
            'openDay' => $openDay,
        ]) ?>

        <?php if ($byDay === []): ?>

            <p class="book-empty">

                Nothing free in the next four weeks<?= $therapist !== null ? ' with ' . h($therapist) : '' ?>.

                <?php if ($nextAvailable !== null): ?>
                    The next free day is
                    <?= $this->Html->link(
                        $nextAvailable['label'],
                        $here + ['?' => ['from' => $nextAvailable['date']]]
                    ) ?>.
                <?php else: ?>
                    Try fewer treatments<?= $therapist !== null ? ', or anyone available' : '' ?>,
                    or call the studio and we will find something.
                <?php endif; ?>

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

                    <div class="book-slots">

                        <?php foreach ($slots as $slot): ?>

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
                            ><?= h($slot['label']) ?></button>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endforeach; ?>

            <?= $this->Form->end() ?>

        <?php endif; ?>

        <p class="book-back">
            <?= $this->Html->link('Change therapist', ['action' => 'staff', $draft->token]) ?>
            <?= $this->Html->link('Change treatments', ['action' => 'index']) ?>
        </p>

    </section>

</main>
