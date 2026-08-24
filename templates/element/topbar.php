<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User|null $identity
 * @var string $selectedDate
 * @var string $range
 */

$today = \Cake\I18n\FrozenDate::today()
    ->format('Y-m-d');
?>

<div class="top-bar">

    <div>
        <h1>
            Welcome back, <?= h($identity->first_name ?? '') ?>
        </h1>

        <p class="subtitle">
            Here's what's happening with the business today.
        </p>
    </div>


    <div class="top-actions">

        <!-- Date + range filter -->
        <form
            class="date-filter-form"
            method="get"
            action="<?= $this->Url->build([
                'controller' => 'Dashboard',
                'action' => 'index'
            ]) ?>"
        >

            <input
                type="date"
                name="date"
                max="<?= h($today) ?>"
                value="<?= h($selectedDate ?? $today) ?>"
                onchange="this.form.submit()"
            >


            <select
                name="range"
                class="dashboard-range-select"
                onchange="this.form.submit()"
            >

                <option
                    value="day"
                    <?= ($range ?? 'day') === 'day'
                        ? 'selected'
                        : '' ?>
                >
                    Daily
                </option>

                <option
                    value="week"
                    <?= ($range ?? '') === 'week'
                        ? 'selected'
                        : '' ?>
                >
                    Weekly
                </option>

                <option
                    value="month"
                    <?= ($range ?? '') === 'month'
                        ? 'selected'
                        : '' ?>
                >
                    Monthly
                </option>

                <option
                    value="year"
                    <?= ($range ?? '') === 'year'
                        ? 'selected'
                        : '' ?>
                >
                    Yearly
                </option>

            </select>

        </form>


        <!-- Export all customers up to selected date -->
        <?= $this->Html->link(
            '<i data-lucide="download"></i> Export customers',
            [
                'controller' => 'Dashboard',
                'action' => 'exportCustomers',
                '?' => [
                    'date' => $selectedDate ?? $today
                ]
            ],
            [
                'escape' => false,
                'class' => 'export-btn'
            ]
        ) ?>

    </div>

</div>
