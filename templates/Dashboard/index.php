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
 */

$this->assign('title', 'Dashboard');
$this->setLayout('dashboard');

$today = \Cake\I18n\FrozenDate::today();

$todayString =
    $today->format('Y-m-d');

$currentMonth =
    (int)$today->format('n');

$currentWeek =
    $today->format('o-\WW');

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
                                    $selectedDay
                                    ?? $todayString
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
                                    $selectedWeek
                                    ?? $currentWeek
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
                                            $selectedMonth
                                            ?? $currentMonth
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


                <!-- Total Customers -->
                <div
                    class="dashboard-widget widget-small"
                    data-widget="total-customers"
                    data-slot="1"
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

                </div>


                <!-- New Customers -->
                <div
                    class="dashboard-widget widget-small"
                    data-widget="new-customers"
                    data-slot="2"
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

                        <!-- Header -->
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

                            <!-- Empty state -->
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

                            <!-- Customer table -->
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
                                        $daysSinceLastBooking = $item['daysSinceLastBooking'];

                                        $customerName = trim(
                                            ($customer->given_name ?? $customer->first_name ?? '')
                                            . ' '
                                            . ($customer->family_name ?? $customer->last_name ?? '')
                                        );

                                        if ($customerName === '') {
                                            $customerName = 'Unknown Customer';
                                        }
                                        ?>

                                        <tr>

                                            <!-- Customer -->
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


                                            <!-- Email -->
                                            <td>

                                <span class="rebooking-email">

                                    <?= h(
                                        $customer->email_address
                                        ?? $customer->email
                                        ?? 'No email'
                                    ) ?>

                                </span>

                                            </td>


                                            <!-- Last booking -->
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


                                            <!-- Inactive -->
                                            <td>

                                                <strong class="rebooking-days">

                                                    <?= h($daysSinceLastBooking) ?>
                                                    days

                                                </strong>

                                            </td>


                                            <!-- Status -->
                                            <td>

                                <span class="rebooking-status">

                                    <i data-lucide="clock"></i>

                                    Needs Rebooking

                                </span>

                                            </td>


                                            <!-- Action -->
                                            <td>

                                                <?= $this->Form->postLink(
                                                    '<i data-lucide="mail"></i> Send Reminder',
                                                    [
                                                        'controller' => 'Dashboard',
                                                        'action' => 'sendRebookingReminder',
                                                        $customer->id,
                                                    ],
                                                    [
                                                        'escape' => false,
                                                        'class' => 'rebooking-reminder-btn',
                                                        'confirm' => 'Send a TEST reminder email for ' . $customerName . '?',
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
