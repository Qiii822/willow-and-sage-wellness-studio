<?php
$rangeLabel = match ($range ?? 'day') {
    'week' => 'week',
    'month' => 'month',
    'year' => 'year',
    default => 'day',
};

$comparisonLabel = match ($range ?? 'day') {
    'week' => 'previous week',
    'month' => 'previous month',
    'year' => 'previous year',
    default => 'previous day',
};
?>

<div class="stat-card">
    <div class="stat-header">

        <div>
            <p class="stat-title">
                New Customers
            </p>

            <h2 class="stat-value">
                <?= h($customersSelectedDate ?? 0) ?>
            </h2>

            <span class="stat-badge">
                new this <?= h($rangeLabel) ?>
            </span>

            <div class="stat-comparison">
                <span class="stat-comparison-up">
                    ● <?= h($customersSelectedDate ?? 0) ?> new
                    vs <?= h($comparisonLabel) ?>
                </span>
            </div>
        </div>

        <div class="stat-icon stat-icon-green">
            <i data-lucide="user-plus"></i>
        </div>

    </div>
</div>
