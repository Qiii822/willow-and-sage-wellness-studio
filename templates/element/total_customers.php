<?php
$rangeLabel = match ($range ?? 'day') {
    'week' => 'week',
    'month' => 'month',
    'year' => 'year',
    default => 'day',
};
?>

<div class="stat-card">
    <div class="stat-header">
        <div>
            <p class="stat-title">Total Customers</p>

            <h2 class="stat-value">
                <?= h($totalCustomers ?? 0) ?>
            </h2>

            <span class="stat-badge">
                as of selected <?= h($rangeLabel) ?>
            </span>
        </div>

        <div class="stat-icon stat-icon-blue">
            <i data-lucide="users"></i>
        </div>
    </div>
</div>
