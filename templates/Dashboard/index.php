<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User|null $identity
 * @var string $range
 * @var string|null $selectedDay
 * @var string|null $selectedWeek
 * @var int|null $selectedYear
 * @var int|null $selectedMonth
 * @var string|null $startDate
 * @var string|null $endDate
 * @var array $availableYears
 * @var array $rebookingCustomers
 * @var int $inactiveThresholdDays
 * @var array $customerGrowthLabels
 * @var array $customerGrowthValues
 * @var array $bookingTrendLabels
 * @var array $bookingTrendValues
 * @var array $bookingStatusLabels
 * @var array $bookingStatusValues
 */

$this->assign('title', 'Dashboard');
$this->setLayout('dashboard');

$today = \Cake\I18n\FrozenDate::today();

$todayString = $today->format('Y-m-d');

$currentMonth = (int)$today->format('n');

$currentWeek = $today->format('o-\WW');

$months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid p-0">

    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">

            <!-- Dashboard heading -->
            <div class="dashboard-heading">

                <div class="dashboard-heading-text">

                    <h1>
                        Welcome back, <?= h($identity->first_name ?? '') ?>
                    </h1>

                    <p>
                        Here's what's happening with the business today.
                    </p>

                </div>

                <div class="dashboard-toolbar">

                    <form
                        class="date-filter-form"
                        method="get"
                        action="<?= $this->Url->build([
                            'controller' => 'Dashboard',
                            'action' => 'index'
                        ]) ?>"
                    >

                        <!-- Daily -->
                        <div
                            class="filter-extra"
                            data-filter-panel="day"
                        >
                            <input
                                type="date"
                                name="day"
                                max="<?= h($todayString) ?>"
                                value="<?= h(
                                    $selectedDay ?? $todayString
                                ) ?>"
                            >
                        </div>

                        <!-- Weekly -->
                        <div
                            class="filter-extra"
                            data-filter-panel="week"
                        >
                            <input
                                type="week"
                                name="week"
                                max="<?= h($currentWeek) ?>"
                                value="<?= h(
                                    $selectedWeek ?? $currentWeek
                                ) ?>"
                            >
                        </div>

                        <!-- Monthly -->
                        <div
                            class="filter-extra filter-month"
                            data-filter-panel="month"
                        >

                            <select
                                name="month_year"
                                class="dashboard-filter-select"
                            >

                                <?php foreach ($availableYears as $year): ?>

                                    <option
                                        value="<?= h($year) ?>"
                                        <?= (int)$selectedYear === (int)$year
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= h($year) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <select
                                name="month"
                                class="dashboard-filter-select"
                            >

                                <?php foreach ($months as $number => $label): ?>

                                    <option
                                        value="<?= h($number) ?>"
                                        <?= (int)(
                                            $selectedMonth ?? $currentMonth
                                        ) === $number
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= h($label) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <!-- Yearly -->
                        <div
                            class="filter-extra"
                            data-filter-panel="year"
                        >

                            <select
                                name="selected_year"
                                class="dashboard-filter-select"
                            >

                                <?php foreach ($availableYears as $year): ?>

                                    <option
                                        value="<?= h($year) ?>"
                                        <?= (int)$selectedYear === (int)$year
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= h($year) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <!-- Custom -->
                        <div
                            class="custom-date-range"
                            id="custom-date-range"
                            data-filter-panel="custom"
                        >

                            <div class="custom-date-field">

                                <label for="start-date">
                                    Start
                                </label>

                                <input
                                    type="date"
                                    id="start-date"
                                    name="start_date"
                                    max="<?= h($todayString) ?>"
                                    value="<?= h($startDate ?? '') ?>"
                                >

                            </div>

                            <div class="custom-date-field">

                                <label for="end-date">
                                    End
                                </label>

                                <input
                                    type="date"
                                    id="end-date"
                                    name="end_date"
                                    max="<?= h($todayString) ?>"
                                    value="<?= h($endDate ?? '') ?>"
                                >

                            </div>

                        </div>

                        <!-- Main range selector -->
                        <select
                            name="range"
                            id="dashboard-range-select"
                            class="dashboard-range-select"
                        >

                            <option
                                value="today"
                                <?= ($range ?? 'today') === 'today'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Today
                            </option>

                            <option
                                value="day"
                                <?= ($range ?? '') === 'day'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Daily
                            </option>

                            <option
                                value="week"
                                <?= ($range ?? '') === 'week'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Weekly
                            </option>

                            <option
                                value="month"
                                <?= ($range ?? '') === 'month'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Monthly
                            </option>

                            <option
                                value="year"
                                <?= ($range ?? '') === 'year'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Yearly
                            </option>

                            <option
                                value="custom"
                                <?= ($range ?? '') === 'custom'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Custom
                            </option>

                        </select>

                        <button
                            type="submit"
                            class="custom-date-apply"
                            id="dashboard-filter-apply"
                        >
                            Apply
                        </button>

                    </form>

                    <!-- Add widget -->
                    <button
                        type="button"
                        class="dashboard-customise-btn"
                        id="add-widget-btn"
                    >
                        <i data-lucide="plus"></i>
                        Add widget
                    </button>

                    <!-- Edit layout -->
                    <button
                        type="button"
                        class="dashboard-customise-btn"
                        id="edit-layout-btn"
                    >
                        <i data-lucide="pencil"></i>
                        Edit layout
                    </button>

                </div>

            </div>

            <!-- Dashboard Grid -->
            <div
                id="dashboard-widgets"
                class="dashboard-widget-grid"
            >

                <!-- Drop Slots -->
                <div
                    class="dashboard-drop-slot"
                    data-slot="1"
                ></div>

                <div
                    class="dashboard-drop-slot"
                    data-slot="2"
                ></div>

                <div
                    class="dashboard-drop-slot"
                    data-slot="3"
                ></div>

                <div
                    class="dashboard-drop-slot"
                    data-slot="4"
                ></div>

                <!-- Total Customers -->
                <div
                    class="dashboard-widget widget-card widget-small"
                    data-widget="total-customers"
                >

                    <div class="widget-edit-controls">

                        <button
                            type="button"
                            class="widget-drag-handle"
                            title="Move widget"
                        >
                            <i data-lucide="grip-vertical"></i>
                        </button>

                        <button
                            type="button"
                            class="widget-remove-btn"
                            title="Remove widget"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>

                    </div>

                    <?= $this->element('total_customers') ?>

                    <button
                        type="button"
                        class="widget-resize-handle"
                        title="Resize widget"
                        aria-label="Resize widget"
                    >
                        <i data-lucide="move-horizontal"></i>
                    </button>

                </div>

                <!-- New Customers -->
                <div
                    class="dashboard-widget widget-card widget-small"
                    data-widget="new-customers"
                >

                    <div class="widget-edit-controls">

                        <button
                            type="button"
                            class="widget-drag-handle"
                            title="Move widget"
                        >
                            <i data-lucide="grip-vertical"></i>
                        </button>

                        <button
                            type="button"
                            class="widget-remove-btn"
                            title="Remove widget"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>

                    </div>

                    <?= $this->element('new_customers') ?>

                    <button
                        type="button"
                        class="widget-resize-handle"
                        title="Resize widget"
                        aria-label="Resize widget"
                    >
                        <i data-lucide="move-horizontal"></i>
                    </button>

                </div>

                <!-- Total Bookings -->
                <div
                    class="dashboard-widget widget-card widget-small"
                    data-widget="total-bookings"
                >

                    <div class="widget-edit-controls">

                        <button
                            type="button"
                            class="widget-drag-handle"
                            title="Move widget"
                        >
                            <i data-lucide="grip-vertical"></i>
                        </button>

                        <button
                            type="button"
                            class="widget-remove-btn"
                            title="Remove widget"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>

                    </div>

                    <?= $this->element('total_bookings') ?>

                    <button
                        type="button"
                        class="widget-resize-handle"
                        title="Resize widget"
                        aria-label="Resize widget"
                    >
                        <i data-lucide="move-horizontal"></i>
                    </button>

                </div>

                <!-- Total Income -->
                <div
                    class="dashboard-widget widget-card widget-small"
                    data-widget="total-income"
                >

                    <div class="widget-edit-controls">

                        <button
                            type="button"
                            class="widget-drag-handle"
                            title="Move widget"
                        >
                            <i data-lucide="grip-vertical"></i>
                        </button>

                        <button
                            type="button"
                            class="widget-remove-btn"
                            title="Remove widget"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>

                    </div>

                    <?= $this->element('total_income') ?>

                    <button
                        type="button"
                        class="widget-resize-handle"
                        title="Resize widget"
                        aria-label="Resize widget"
                    >
                        <i data-lucide="move-horizontal"></i>
                    </button>

                </div>

                <!-- Customer Growth -->
                <div
                    class="dashboard-widget widget-wide"
                    data-widget="customer-growth"
                >

                    <div class="widget-edit-controls">

                        <button
                            type="button"
                            class="widget-drag-handle"
                            title="Move widget"
                        >
                            <i data-lucide="grip-vertical"></i>
                        </button>

                        <button
                            type="button"
                            class="widget-remove-btn"
                            title="Remove widget"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>

                    </div>

                    <div class="customer-growth-widget">

                        <div class="customer-growth-header">

                            <div>

                                <h2>
                                    Customer Growth
                                </h2>

                                <p>
                                    New customers added during
                                    <?= h($customerGrowthYear) ?>
                                </p>

                            </div>

                            <form
                                method="get"
                                action="<?= $this->Url->build([
                                    'controller' => 'Dashboard',
                                    'action' => 'index',
                                ]) ?>"
                            >

                                <select
                                    name="customer_growth_year"
                                    class="dashboard-filter-select"
                                    onchange="this.form.submit()"
                                >

                                    <?php foreach ($availableYears as $year): ?>

                                        <option
                                            value="<?= h($year) ?>"
                                            <?= (int)$customerGrowthYear === (int)$year
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= h($year) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </form>

                        </div>

                        <div class="customer-growth-chart">

                            <canvas
                                id="customerGrowthChart"
                            ></canvas>

                        </div>

                    </div>

                </div>

                <!-- Booking Trends -->
                <div
                    class="dashboard-widget widget-wide"
                    data-widget="booking-trends"
                >

                    <div class="widget-edit-controls">

                        <button
                            type="button"
                            class="widget-drag-handle"
                            title="Move widget"
                        >
                            <i data-lucide="grip-vertical"></i>
                        </button>

                        <button
                            type="button"
                            class="widget-remove-btn"
                            title="Remove widget"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>

                    </div>

                    <div class="booking-trends-widget">

                        <div class="booking-trends-header">

                            <div>

                                <h2>
                                    Booking Trends
                                </h2>

                                <p>
                                    Bookings during
                                    <?= h($selectedYear) ?>
                                </p>

                            </div>

                        </div>

                        <div class="booking-trends-chart">

                            <canvas
                                id="bookingTrendsChart"
                            ></canvas>

                        </div>

                    </div>

                </div>

                <!-- Booking Status -->
                <div
                    class="dashboard-widget widget-wide"
                    data-widget="booking-status"
                >

                    <div class="widget-edit-controls">

                        <button
                            type="button"
                            class="widget-drag-handle"
                            title="Move widget"
                        >
                            <i data-lucide="grip-vertical"></i>
                        </button>

                        <button
                            type="button"
                            class="widget-remove-btn"
                            title="Remove widget"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>

                    </div>

                    <div class="booking-status-widget">

                        <div class="booking-status-header">

                            <div>

                                <h2>
                                    Booking Status
                                </h2>

                                <p>
                                    Booking status breakdown during
                                    <?= h($selectedYear) ?>
                                </p>

                            </div>

                        </div>

                        <div class="booking-status-chart">

                            <canvas
                                id="bookingStatusChart"
                            ></canvas>

                        </div>

                    </div>

                </div>

                <!-- Recent Customers -->
                <div
                    class="dashboard-widget widget-wide"
                    data-widget="recent-customers"
                >

                    <div class="widget-edit-controls">

                        <button
                            type="button"
                            class="widget-drag-handle"
                            title="Move widget"
                        >
                            <i data-lucide="grip-vertical"></i>
                        </button>

                        <button
                            type="button"
                            class="widget-remove-btn"
                            title="Remove widget"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>

                    </div>

                    <?= $this->element('recent_customers') ?>

                    <button
                        type="button"
                        class="widget-resize-handle"
                        title="Resize widget"
                        aria-label="Resize widget"
                    >
                        <i data-lucide="move-horizontal"></i>
                    </button>

                </div>

                <!-- Customer Rebooking -->
                <div
                    class="dashboard-widget widget-wide"
                    data-widget="customer-rebooking"
                >

                    <div class="widget-edit-controls">

                        <button
                            type="button"
                            class="widget-drag-handle"
                            title="Move widget"
                        >
                            <i data-lucide="grip-vertical"></i>
                        </button>

                        <button
                            type="button"
                            class="widget-remove-btn"
                            title="Remove widget"
                        >
                            <i data-lucide="trash-2"></i>
                        </button>

                    </div>

                    <div class="rebooking-widget">

                        <div class="rebooking-widget-header">

                            <div>

                                <h2>
                                    Customer Rebooking
                                </h2>

                                <p>
                                    Customers who have not booked for
                                    <?= h($inactiveThresholdDays) ?>
                                    days or more.
                                </p>

                            </div>

                            <span class="rebooking-count">
                                <?= count($rebookingCustomers) ?>
                                customer<?= count($rebookingCustomers) === 1 ? '' : 's' ?>
                            </span>

                        </div>

                        <?php if (empty($rebookingCustomers)): ?>

                            <div class="rebooking-empty">

                                <div class="rebooking-empty-icon">
                                    <i data-lucide="calendar-check"></i>
                                </div>

                                <h3>
                                    No inactive customers
                                </h3>

                                <p>
                                    All customers with previous bookings
                                    have booked within the last
                                    <?= h($inactiveThresholdDays) ?>
                                    days.
                                </p>

                            </div>

                        <?php else: ?>

                            <div class="rebooking-table-wrapper">

                                <table class="rebooking-table">

                                    <thead>

                                    <tr>
                                        <th>Customer</th>
                                        <th>Email</th>
                                        <th>Last Booking</th>
                                        <th>Inactive For</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>

                                    </thead>

                                    <tbody>

                                    <?php foreach ($rebookingCustomers as $item): ?>

                                        <?php
                                        $customer = $item['customer'];
                                        $lastBooking = $item['lastBooking'];
                                        $daysSinceLastBooking =
                                            $item['daysSinceLastBooking'];

                                        $customerName = trim(
                                            ($customer->given_name
                                                ?? $customer->first_name
                                                ?? '')
                                            . ' '
                                            . ($customer->family_name
                                                ?? $customer->last_name
                                                ?? '')
                                        );

                                        if ($customerName === '') {
                                            $customerName =
                                                'Unknown Customer';
                                        }
                                        ?>

                                        <tr>

                                            <td>

                                                <div class="rebooking-customer">

                                                    <div class="rebooking-avatar">

                                                        <?= h(
                                                            strtoupper(
                                                                substr(
                                                                    $customerName,
                                                                    0,
                                                                    1
                                                                )
                                                            )
                                                        ) ?>

                                                    </div>

                                                    <strong>
                                                        <?= h($customerName) ?>
                                                    </strong>

                                                </div>

                                            </td>

                                            <td>

                                                <span class="rebooking-email">

                                                    <?= h(
                                                        $customer->email_address
                                                        ?? $customer->email
                                                        ?? 'No email'
                                                    ) ?>

                                                </span>

                                            </td>

                                            <td>

                                                <?php if (!empty($lastBooking->start_at)): ?>

                                                    <?= h(
                                                        $lastBooking
                                                            ->start_at
                                                            ->format('d M Y')
                                                    ) ?>

                                                <?php else: ?>

                                                    —

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <strong class="rebooking-days">

                                                    <?= h($daysSinceLastBooking) ?>
                                                    days

                                                </strong>

                                            </td>

                                            <td>

                                                <span class="rebooking-status">

                                                    <i data-lucide="clock"></i>

                                                    Needs Rebooking

                                                </span>

                                            </td>

                                            <td>

                                                <?= $this->Form->postLink(
                                                    '<i data-lucide="mail"></i> Send Reminder',
                                                    [
                                                        'controller' =>
                                                            'Dashboard',
                                                        'action' =>
                                                            'sendRebookingReminder',
                                                        $customer->id,
                                                    ],
                                                    [
                                                        'escape' => false,
                                                        'class' =>
                                                            'rebooking-reminder-btn',
                                                        'confirm' =>
                                                            'Send a TEST reminder email for '
                                                            . $customerName
                                                            . '?',
                                                    ]
                                                ) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </div>

                    <button
                        type="button"
                        class="widget-resize-handle"
                        title="Resize widget"
                        aria-label="Resize widget"
                    >
                        <i data-lucide="move-horizontal"></i>
                    </button>

                </div>

            </div>

            <!-- Widget Library -->
            <div
                class="widget-modal"
                id="widget-modal"
            >

                <div
                    class="widget-modal-overlay"
                    id="widget-modal-overlay"
                ></div>

                <div class="widget-modal-content">

                    <div class="widget-modal-header">

                        <div>

                            <h2>
                                Widget Library
                            </h2>

                            <p>
                                Choose widgets to add to your dashboard.
                            </p>

                        </div>

                        <button
                            type="button"
                            class="widget-modal-close"
                            id="widget-modal-close"
                            aria-label="Close widget library"
                        >
                            <i data-lucide="x"></i>
                        </button>

                    </div>

                    <div class="widget-library-grid">

                        <!-- Total Customers -->
                        <button
                            type="button"
                            class="widget-library-item"
                            data-widget="total-customers"
                        >

                            <div class="widget-library-icon">
                                <i data-lucide="users"></i>
                            </div>

                            <h3>
                                Total Customers
                            </h3>

                            <p>
                                View the total number of customers.
                            </p>

                        </button>

                        <!-- New Customers -->
                        <button
                            type="button"
                            class="widget-library-item"
                            data-widget="new-customers"
                        >

                            <div class="widget-library-icon">
                                <i data-lucide="user-plus"></i>
                            </div>

                            <h3>
                                New Customers
                            </h3>

                            <p>
                                View customers added during the selected period.
                            </p>

                        </button>

                        <!-- Total Bookings -->
                        <button
                            type="button"
                            class="widget-library-item"
                            data-widget="total-bookings"
                        >

                            <div class="widget-library-icon">
                                <i data-lucide="calendar-check"></i>
                            </div>

                            <h3>
                                Total Bookings
                            </h3>

                            <p>
                                View the total number of bookings.
                            </p>

                        </button>

                        <!-- Total Income -->
                        <button
                            type="button"
                            class="widget-library-item"
                            data-widget="total-income"
                        >

                            <div class="widget-library-icon">
                                <i data-lucide="dollar-sign"></i>
                            </div>

                            <h3>
                                Total Income
                            </h3>

                            <p>
                                View total income from completed payments.
                            </p>

                        </button>

                        <!-- Customer Growth -->
                        <button
                            type="button"
                            class="widget-library-item"
                            data-widget="customer-growth"
                        >

                            <div class="widget-library-icon">
                                <i data-lucide="chart-line"></i>
                            </div>

                            <h3>
                                Customer Growth
                            </h3>

                            <p>
                                View new customer growth throughout the year.
                            </p>

                        </button>

                        <!-- Booking Trends -->
                        <button
                            type="button"
                            class="widget-library-item"
                            data-widget="booking-trends"
                        >

                            <div class="widget-library-icon">
                                <i data-lucide="chart-column"></i>
                            </div>

                            <h3>
                                Booking Trends
                            </h3>

                            <p>
                                View monthly booking activity throughout the year.
                            </p>

                        </button>

                        <!-- Booking Status -->
                        <button
                            type="button"
                            class="widget-library-item"
                            data-widget="booking-status"
                        >

                            <div class="widget-library-icon">
                                <i data-lucide="pie-chart"></i>
                            </div>

                            <h3>
                                Booking Status
                            </h3>

                            <p>
                                View accepted, cancelled and other bookings.
                            </p>

                        </button>

                        <!-- Recent Customers -->
                        <button
                            type="button"
                            class="widget-library-item"
                            data-widget="recent-customers"
                        >

                            <div class="widget-library-icon">
                                <i data-lucide="list"></i>
                            </div>

                            <h3>
                                Recent Customers
                            </h3>

                            <p>
                                View the latest customers imported from Square.
                            </p>

                        </button>

                        <!-- Customer Rebooking -->
                        <button
                            type="button"
                            class="widget-library-item"
                            data-widget="customer-rebooking"
                        >

                            <div class="widget-library-icon">
                                <i data-lucide="calendar-clock"></i>
                            </div>

                            <h3>
                                Customer Rebooking
                            </h3>

                            <p>
                                Find customers who have not booked recently
                                and send rebooking reminders.
                            </p>

                        </button>

                    </div>

                </div>

            </div>

        </main>

    </div>

</div>

<script>
    document.addEventListener(
        'DOMContentLoaded',
        function () {

            /*
             * Customer Growth Chart
             */
            const customerCanvas =
                document.getElementById(
                    'customerGrowthChart'
                );

            if (customerCanvas) {

                const customerLabels =
                    <?= json_encode(
                        $customerGrowthLabels
                    ) ?>;

                const customerValues =
                    <?= json_encode(
                        $customerGrowthValues
                    ) ?>;

                new Chart(
                    customerCanvas,
                    {
                        type: 'line',

                        data: {
                            labels: customerLabels,

                            datasets: [
                                {
                                    label: 'New Customers',

                                    data: customerValues,

                                    borderWidth: 2,

                                    tension: 0.35,

                                    fill: false,

                                    pointRadius: 4,

                                    pointHoverRadius: 6
                                }
                            ]
                        },

                        options: {
                            responsive: true,

                            maintainAspectRatio: false,

                            plugins: {
                                legend: {
                                    display: false
                                }
                            },

                            scales: {
                                y: {
                                    beginAtZero: true,

                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    }
                );

            }

            /*
             * Booking Trends Chart
             */
            const bookingCanvas =
                document.getElementById(
                    'bookingTrendsChart'
                );

            if (bookingCanvas) {

                const bookingLabels =
                    <?= json_encode(
                        $bookingTrendLabels
                    ) ?>;

                const bookingValues =
                    <?= json_encode(
                        $bookingTrendValues
                    ) ?>;

                new Chart(
                    bookingCanvas,
                    {
                        type: 'bar',

                        data: {
                            labels: bookingLabels,

                            datasets: [
                                {
                                    label: 'Bookings',

                                    data: bookingValues,

                                    borderWidth: 1,

                                    borderRadius: 6
                                }
                            ]
                        },

                        options: {
                            responsive: true,

                            maintainAspectRatio: false,

                            plugins: {
                                legend: {
                                    display: false
                                }
                            },

                            scales: {
                                y: {
                                    beginAtZero: true,

                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    }
                );

            }

            /*
             * BOOKING STATUS CHART
             */
            const bookingStatusCanvas =
                document.getElementById(
                    'bookingStatusChart'
                );

            if (bookingStatusCanvas) {

                const bookingStatusLabels =
                    <?= json_encode(
                        $bookingStatusLabels
                    ) ?>;

                const bookingStatusValues =
                    <?= json_encode(
                        $bookingStatusValues
                    ) ?>;

                new Chart(
                    bookingStatusCanvas,
                    {
                        type: 'doughnut',

                        data: {
                            labels: bookingStatusLabels,

                            datasets: [
                                {
                                    data: bookingStatusValues,

                                    borderWidth: 2
                                }
                            ]
                        },

                        options: {
                            responsive: true,

                            maintainAspectRatio: false,

                            cutout: '65%',

                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    }
                );

            }
        }
    );
</script>
