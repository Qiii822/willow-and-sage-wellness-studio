<?php
/**
 * The week's bookings, laid out by when they are.
 *
 * The blocks are positioned from minutes rather than from a table row per
 * slot: appointments do not start on the half hour just because the grid
 * does, and a 75 minute treatment has to be able to cover an hour and a
 * quarter of the column.
 *
 * @var \App\View\AppView $this
 * @var array<int, array<int, array<string, mixed>>> $days
 * @var int $firstHour
 * @var int $lastHour
 * @var \Cake\I18n\FrozenTime $weekStart
 * @var string $previous
 * @var string $next
 * @var string $today
 * @var int $count
 */

$this->assign('title', 'Bookings calendar');
$this->setLayout('dashboard');

$this->Html->css('bookings_calendar', ['block' => true]);

/*
 * One row per hour the grid covers, labelled by the hour it opens - so
 * 8:00-19:00 is eleven rows labelled 8am to 6pm, not twelve. The blocks are
 * positioned inside that same span, and a twelfth row would leave the grid
 * an hour taller than the times it can hold.
 */
$hours = range($firstHour, $lastHour - 1);

$weekEnd = $weekStart->addDays(6);
$span = $weekStart->format('F') === $weekEnd->format('F')
    ? $weekStart->format('j') . ' - ' . $weekEnd->format('j F Y')
    : $weekStart->format('j M') . ' - ' . $weekEnd->format('j M Y');
?>

<div class="container-fluid p-0">
    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">

            <div class="top-bar">
                <div>
                    <h1>Bookings</h1>
                    <p class="subtitle">All bookings synced from Square.</p>
                </div>
            </div>

            <div class="card table-card">

                <div class="table-header">
                    <div>
                        <h3>Week of <?= h($span) ?></h3>
                        <p><?= $count ?> booked</p>
                    </div>

                    <?= $this->element('bookings/views', ['current' => 'calendar']) ?>
                </div>

                <div class="calendar-toolbar">

                    <?= $this->Html->link(
                        '<span aria-hidden="true">&#8592;</span>'
                        . '<span class="visually-hidden">Previous week</span>',
                        ['action' => 'calendar', '?' => ['from' => $previous]],
                        ['class' => 'calendar-step', 'escape' => false]
                    ) ?>

                    <?= $this->Html->link(
                        'This week',
                        ['action' => 'calendar', '?' => ['from' => $today]],
                        ['class' => 'calendar-today']
                    ) ?>

                    <?= $this->Html->link(
                        '<span aria-hidden="true">&#8594;</span>'
                        . '<span class="visually-hidden">Next week</span>',
                        ['action' => 'calendar', '?' => ['from' => $next]],
                        ['class' => 'calendar-step', 'escape' => false]
                    ) ?>

                    <p class="calendar-note">
                        Cancelled and declined appointments are left off.
                    </p>

                </div>

                <div class="calendar-scroll">

                    <div class="calendar-grid">

                        <div class="calendar-corner"></div>

                        <?php foreach (range(0, 6) as $column): ?>
                            <?php
                            $day = $weekStart->addDays($column);
                            $isToday = $day->format('Y-m-d') === $today;
                            ?>
                            <div class="calendar-head<?= $isToday ? ' is-today' : '' ?>">
                                <span class="calendar-head-day"><?= h($day->format('D')) ?></span>
                                <span class="calendar-head-date"><?= h($day->format('j/m')) ?></span>
                            </div>
                        <?php endforeach; ?>

                        <div class="calendar-times">
                            <?php foreach ($hours as $hour): ?>
                                <?php /*
                                 * The label sits at the top of its own hour,
                                 * so it lines up with the rule that starts it
                                 * rather than floating in the middle.
                                 */ ?>
                                <div class="calendar-time">
                                    <span><?= h((new DateTimeImmutable())->setTime($hour, 0)->format('g a')) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach (range(0, 6) as $column): ?>

                            <?php
                            $day = $weekStart->addDays($column);
                            $isToday = $day->format('Y-m-d') === $today;
                            ?>

                            <div class="calendar-day<?= $isToday ? ' is-today' : '' ?>">

                                <?php foreach ($hours as $hour): ?>
                                    <div class="calendar-slot"></div>
                                <?php endforeach; ?>

                                <?php foreach ($days[$column] as $entry): ?>

                                    <?php
                                    // Minutes from the top of the grid, which
                                    // the stylesheet turns into pixels.
                                    $offset = $entry['start_minutes'] - ($firstHour * 60);
                                    ?>

                                    <?php // A link, so the details - and taking payment - are a click away. ?>
                                    <a
                                        href="<?= $this->Url->build(['action' => 'view', $entry['booking']->id]) ?>"
                                        class="calendar-booking<?= $entry['booking']->status === 'pending' ? ' is-pending' : '' ?>"
                                        style="--start: <?= $offset ?>; --length: <?= $entry['duration'] ?>;"
                                    >
                                        <p class="calendar-booking-time">
                                            <?= h($entry['label']) ?>
                                            <?php if ($entry['paid']): ?>
                                                <span class="calendar-booking-paid">Paid</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="calendar-booking-name"><?= h($entry['name']) ?></p>
                                        <?php if ($entry['treatments'] !== []): ?>
                                            <p class="calendar-booking-treatment">
                                                <?= h(implode(', ', $entry['treatments'])) ?>
                                            </p>
                                        <?php endif; ?>
                                    </a>

                                <?php endforeach; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

                <?php if ($count === 0): ?>
                    <p class="calendar-empty">
                        Nothing booked this week.
                    </p>
                <?php endif; ?>

            </div>

        </main>

    </div>
</div>
