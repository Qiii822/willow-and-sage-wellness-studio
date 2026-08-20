<?php
/**
 * @var \App\View\AppView $this
 * @var int $totalCustomers
 * @var int $customersSelectedDate
 */
?>

<div class="row g-4 stats">

    <!-- Card 1 -->
    <div class="col-12 col-md-6">
        <div class="card">

            <div class="stat-header">
                <h3>Total Customers</h3>

                <div class="stat-icon icon-blue">
                    <i data-lucide="users"></i>
                </div>
            </div>

            <h2><?= number_format($totalCustomers) ?></h2>

            <p class="positive">
                as of selected date
            </p>

        </div>
    </div>

    <!-- Card 2 -->
    <div class="col-12 col-md-6">
        <div class="card">

            <div class="stat-header">
                <h3>New Customers</h3>

                <div class="stat-icon icon-green">
                    <i data-lucide="user-plus"></i>
                </div>
            </div>

            <h2><?= number_format($customersSelectedDate) ?></h2>

            <p class="positive">
                new on selected date
            </p>

        </div>
    </div>

</div>
