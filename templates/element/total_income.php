<?php
$rangeLabel = match ($range ?? 'day') {
    'week' => 'week',
    'month' => 'month',
    'year' => 'year',
    default => 'day',
};

// Amounts are stored as integer minor units (e.g. cents) - currency is
// always AUD, so this only needs to add the decimal point.
$formattedIncome = '$' . number_format(($totalIncomeCents ?? 0) / 100, 2);
?>

<div class="stat-card">
    <div class="stat-header">
        <div>
            <p class="stat-title">Total Income</p>

            <h2 class="stat-value">
                <?= h($formattedIncome) ?>
            </h2>

            <span class="stat-badge">
                as of selected <?= h($rangeLabel) ?>
            </span>
        </div>

        <div class="stat-icon stat-icon-purple">
            <i data-lucide="dollar-sign"></i>
        </div>
    </div>
</div>
