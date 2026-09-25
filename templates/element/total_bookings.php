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
            <p class="stat-title">Total Bookings</p>

            <h2 class="stat-value">
                <?= h($totalBookings ?? 0) ?>
            </h2>

            <span class="stat-badge">
                as of selected <?= h($rangeLabel) ?>
            </span>
        </div>

        <div class="stat-icon stat-icon-amber">
            <i data-lucide="calendar-check"></i>
        </div>
    </div>
</div>
