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

        <?php /*
         * The month that was fetched. The script reads it to decide whether
         * an arrow or a chosen date can be handled here or has to be asked
         * for, so it does not have to guess from what happens to be drawn.
         */ ?>
        <div
            class="book-calendar"
            data-window-from="<?= h($window['from']) ?>"
            data-window-to="<?= h($window['to']) ?>"
        >

            <div class="book-calendar-head">

                <div class="book-calendar-week">

                    <?php /*
                     * Links to the month either side. Inside the fetched
                     * month the script stops them and swaps the week rows
                     * over instead, so only stepping off the ends reloads.
                     *
                     * Where there is no page to link to - the far end of
                     * what Square will book - the control is still rendered,
                     * disabled, because the script may yet find a loaded
                     * week that way and turn it back on.
                     */ ?>
                    <?= $this->element('book/week_step', [
                        'direction' => 'back',
                        'to' => $window['before'],
                        'glyph' => '&#8592;',
                        'label' => 'Previous week',
                        'url' => $here,
                    ]) ?>

                    <?php /*
                     * Names the whole fetched span to begin with, because
                     * that is what a page without scripting shows: every day
                     * of the month, listed. The script narrows it to the
                     * week it puts on show.
                     */ ?>
                    <h2 class="book-week-label" data-week-title><?= h($window['label']) ?></h2>

                    <?= $this->element('book/week_step', [
                        'direction' => 'forward',
                        'to' => $window['after'],
                        'glyph' => '&#8594;',
                        'label' => 'Next week',
                        'url' => $here,
                    ]) ?>

                </div>

                <?php /*
                 * Its own GET form, separate from the one that books a slot.
                 * The browser's own date box is used rather than a custom
                 * one: it already knows the customer's locale, works on a
                 * phone, and needs nothing loaded to do it. A date inside
                 * the fetched month is handled without leaving the page.
                 */ ?>
                <?= $this->Form->create(null, [
                    'type' => 'get',
                    'url' => $here,
                    'class' => 'book-jump',
                    'data-jump' => 'true',
                ]) ?>

                <label for="jump-to">Jump to</label>

                <?= $this->Form->date('from', [
                    'id' => 'jump-to',
                    'class' => 'book-jump-input',
                    'val' => $openDay ?? $window['from'],
                    'min' => $window['earliest'],
                    'max' => $window['latest'],
                ]) ?>

                <?= $this->Form->button('Go', ['class' => 'book-jump-go']) ?>

                <?= $this->Form->end() ?>

            </div>

            <?php /*
             * Every row starts hidden: they are buttons that do nothing
             * until the script runs, and a page without it lists all the
             * days below anyway. The script reveals the week it opens on.
             */ ?>
            <?php foreach ($weeks as $week): ?>

                <ol
                    class="book-calendar-days"
                    data-week="<?= h($week['start']) ?>"
                    data-label="<?= h($week['label']) ?>"
                    hidden
                >

                    <?php foreach ($week['days'] as $day): ?>

                        <?php $open = $day['slots'] > 0; ?>

                        <li>
                            <button
                                type="button"
                                class="book-date<?= $open ? '' : ' is-closed' ?>"
                                data-date="<?= h($day['date']) ?>"
                                <?= $open ? '' : 'disabled' ?>
                            >
                                <span class="book-date-weekday"><?= h($day['weekday']) ?></span>
                                <span class="book-date-day"><?= h($day['day']) ?></span>
                                <span class="book-date-dot" aria-hidden="true"></span>
                                <span class="visually-hidden">
                                    <?= $open
                                        ? h($day['slots']) . ' times available'
                                        : 'no times available' ?>
                                </span>
                            </button>
                        </li>

                    <?php endforeach; ?>

                </ol>

            <?php endforeach; ?>

        </div>

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
            &middot;
            <?= $this->Html->link('Change treatments', ['action' => 'index']) ?>
        </p>

    </section>

</main>
