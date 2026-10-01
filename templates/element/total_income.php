<?php
$incomeRangeLabel = match ($incomeRange ?? 'month') {
    'day' => 'day',
    'week' => 'week',
    'year' => 'year',
    default => 'month',
};

// Amounts are stored as integer minor units (e.g. cents).
$formattedIncome =
    '$' . number_format(
        ($totalIncomeCents ?? 0) / 100,
        2
    );
?>

<div class="stat-card">

    <div class="stat-header">

        <div>

            <p class="stat-title">
                Total Income
            </p>

            <h2 class="stat-value">
                <?= h($formattedIncome) ?>
            </h2>

            <span class="stat-badge">
                This <?= h($incomeRangeLabel) ?>
            </span>

        </div>

        <div class="stat-icon stat-icon-purple">
            <i data-lucide="dollar-sign"></i>
        </div>

    </div>

    <form
        method="get"
        action="<?= $this->Url->build([
            'controller' => 'Dashboard',
            'action' => 'index',
        ]) ?>"
    >

        <select
            name="income_range"
            class="dashboard-filter-select"
            onchange="this.form.submit()"
        >

            <option
                value="day"
                <?= $incomeRange === 'day'
                    ? 'selected'
                    : '' ?>
            >
                Day
            </option>

            <option
                value="week"
                <?= $incomeRange === 'week'
                    ? 'selected'
                    : '' ?>
            >
                Week
            </option>

            <option
                value="month"
                <?= $incomeRange === 'month'
                    ? 'selected'
                    : '' ?>
            >
                Month
            </option>

            <option
                value="year"
                <?= $incomeRange === 'year'
                    ? 'selected'
                    : '' ?>
            >
                Year
            </option>

        </select>

    </form>

</div>
