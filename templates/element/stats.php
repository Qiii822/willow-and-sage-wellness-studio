<div class="row g-4 stats">

    <!-- Card 1 -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card">

            <div class="stat-header">
                <h3>Total Customers</h3>

                <div class="stat-icon icon-blue">
                    <i data-lucide="users"></i>
                </div>
            </div>

            <h2><?= number_format($totalCustomers) ?></h2>

            <p class="positive">
                <?= number_format($customersThisWeek) ?> new this week
            </p>

        </div>
    </div>

    <!-- Card 2 -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card">

            <div class="stat-header">
                <h3>New Customers</h3>

                <div class="stat-icon icon-green">
                    <i data-lucide="user-plus"></i>
                </div>
            </div>

            <h2>86</h2>
            <p class="positive">+8.4%</p>

        </div>
    </div>

    <!-- Card 3 -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card">

            <div class="stat-header">
                <h3>Total Income</h3>

                <div class="stat-icon icon-amber">
                    <i data-lucide="dollar-sign"></i>
                </div>
            </div>

            <h2>$<?= number_format(18420) ?></h2>
            <p class="positive">+10.2%</p>

        </div>
    </div>

</div>
