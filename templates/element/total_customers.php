<div class="stat-card">
    <div class="stat-header">
        <div>
            <p class="stat-title">Total Customers</p>

            <h2 class="stat-value">
                <?= h($totalCustomers ?? 0) ?>
            </h2>

            <span class="stat-badge">
                as of selected date
            </span>
        </div>

        <div class="stat-icon stat-icon-blue">
            <i data-lucide="users"></i>
        </div>
    </div>
</div>
