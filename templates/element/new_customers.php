<div class="stat-card">
    <div class="stat-header">
        <div>
            <p class="stat-title">New Customers</p>

            <h2 class="stat-value">
                <?= h($customersSelectedDate ?? 0) ?>
            </h2>

            <span class="stat-badge">
                new on selected date
            </span>
        </div>

        <div class="stat-icon stat-icon-green">
            <i data-lucide="user-plus"></i>
        </div>
    </div>
</div>
