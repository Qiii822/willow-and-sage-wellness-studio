<?php
/**
 * @var \App\View\AppView $this
 * @var int $totalCustomers
 */
?>


<div class="charts">

    <div class="card chart-card">
        <div class="chart-card-header">
            <div>
                <h3>Customers by Age Groups</h3>
                <p>Customer distribution by age</p>
            </div>
        </div>

        <div class="chart-canvas-wrapper">
            <canvas
                id="ageChart"
                data-total-customers="<?= h($totalCustomers) ?>"
            ></canvas>
        </div>
    </div>

    <div class="card chart-card">
        <div class="chart-card-header">
            <div>
                <h3>Booking Trend</h3>
                <p>Bookings over the last 7 days</p>
            </div>
        </div>

        <div class="chart-canvas-wrapper">
            <canvas id="bookingChart"></canvas>
        </div>
    </div>

</div>
