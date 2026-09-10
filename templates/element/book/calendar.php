<?php
/**
 * The month-at-a-time day picker.
 *
 * Four weeks are rendered and one is on show, so the arrows and the date
 * box work without going back to the server. Stepping outside the fetched
 * month is an ordinary link that loads the next one.
 *
 * Every week row starts hidden: they are buttons that do nothing until
 * book_calendar.js runs, and a page without it lists all the days below
 * anyway under a heading naming the whole span.
 *
 * @var \App\View\AppView $this
 * @var array<int, array{start: string, label: string, slots: int, days: array<int, array{date: string, day: string, weekday: string, slots: int}>}> $weeks
 * @var array{from: string, to: string, earliest: string, latest: string, label: string, before: string|null, after: string|null} $window
 * @var array<int|string, mixed> $url The page the picker belongs to.
 * @var string|null $openDay The day the date box starts on.
 */

$openDay = $openDay ?? null;
?>

<div
    class="book-calendar"
    data-window-from="<?= h($window['from']) ?>"
    data-window-to="<?= h($window['to']) ?>"
>

    <div class="book-calendar-head">

        <div class="book-calendar-week">

            <?= $this->element('book/week_step', [
                'direction' => 'back',
                'to' => $window['before'],
                'glyph' => '&#8592;',
                'label' => 'Previous week',
                'url' => $url,
            ]) ?>

            <?php /*
             * Names the whole fetched span to begin with, because that is
             * what a page without scripting shows: every day of the month,
             * listed. The script narrows it to the week it puts on show.
             */ ?>
            <h2 class="book-week-label" data-week-title><?= h($window['label']) ?></h2>

            <?= $this->element('book/week_step', [
                'direction' => 'forward',
                'to' => $window['after'],
                'glyph' => '&#8594;',
                'label' => 'Next week',
                'url' => $url,
            ]) ?>

        </div>

        <?php /*
         * Its own GET form, separate from the one that picks a slot. The
         * browser's own date box is used rather than a custom one: it
         * already knows the customer's locale, works on a phone, and needs
         * nothing loaded to do it. A date inside the fetched month is
         * handled without leaving the page.
         */ ?>
        <?= $this->Form->create(null, [
            'type' => 'get',
            'url' => $url,
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
