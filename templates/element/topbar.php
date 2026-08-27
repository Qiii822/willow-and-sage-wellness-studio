<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User|null $identity
 * @var string $range
 * @var string|null $startDate
 * @var string|null $endDate
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

        <form
            class="date-filter-form"
            method="get"
            action="<?= $this->Url->build([
                'controller' => 'Dashboard',
                'action' => 'index'
            ]) ?>"
        >

            <!-- Range -->
            <select
                name="range"
                id="dashboard-range-select"
                class="dashboard-range-select"
                onchange="handleRangeChange(this)"
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

                <option
                    value="custom"
                    <?= ($range ?? '') === 'custom'
                        ? 'selected'
                        : '' ?>
                >
                    Custom
                </option>

            </select>


            <!-- Custom dates -->
            <div
                class="custom-date-range
                <?= ($range ?? '') === 'custom'
                    ? 'show'
                    : '' ?>"
                id="custom-date-range"
            >

                <div class="custom-date-field">

                    <label for="start-date">
                        Start
                    </label>

                    <input
                        type="date"
                        id="start-date"
                        name="start_date"
                        max="<?= h($today) ?>"
                        value="<?= h($startDate ?? '') ?>"
                    >

                </div>


                <div class="custom-date-field">

                    <label for="end-date">
                        End
                    </label>

                    <input
                        type="date"
                        id="end-date"
                        name="end_date"
                        max="<?= h($today) ?>"
                        value="<?= h($endDate ?? '') ?>"
                    >

                </div>


                <button
                    type="submit"
                    class="custom-date-apply"
                >
                    Apply
                </button>

            </div>

        </form>

    </div>

</div>
