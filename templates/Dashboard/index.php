<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User|null $identity
 * @var string $range
 * @var string|null $startDate
 * @var string|null $endDate
 */

$this->assign('title', 'Dashboard');
$this->setLayout('dashboard');

$today = \Cake\I18n\FrozenDate::today()
    ->format('Y-m-d');
?>

<div class="container-fluid p-0">

    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">


            <!-- =====================================================
                 Dashboard Heading + Toolbar
                 ===================================================== -->

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

                    <!-- Range filter -->
                    <form
                        class="date-filter-form"
                        method="get"
                        action="<?= $this->Url->build([
                            'controller' => 'Dashboard',
                            'action' => 'index'
                        ]) ?>"
                    >

                        <select
                            name="range"
                            id="dashboard-range-select"
                            class="dashboard-range-select"
                            onchange="handleRangeChange(this)"
                        >

                            <option
                                value="day"
                                <?= ($range ?? 'day') === 'day'
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


                        <!-- Custom date range -->
                        <div
                            class="custom-date-range
                            <?= ($range ?? '') === 'custom'
                                ? 'show'
                                : '' ?>"
                            id="custom-date-range"
                        >

                            <div class="custom-date-field">

                                <label for="start-date">
                                    Start
                                </label>

                                <input
                                    type="date"
                                    id="start-date"
                                    name="start_date"
                                    max="<?= h($today) ?>"
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
                                    max="<?= h($today) ?>"
                                    value="<?= h($endDate ?? '') ?>"
                                >

                            </div>


                            <button
                                type="submit"
                                class="custom-date-apply"
                            >
                                Apply
                            </button>

                        </div>

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


            <!-- =====================================================
                 Dashboard Grid
                 ===================================================== -->

            <div
                id="dashboard-widgets"
                class="dashboard-widget-grid"
            >

                <!-- Drop slots -->
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


                <!-- Total Bookings -->
                <div
                    class="dashboard-widget widget-small"
                    data-widget="total-bookings"
                    data-slot="3"
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

                </div>


                <!-- Total Income -->
                <div
                    class="dashboard-widget widget-small"
                    data-widget="total-income"
                    data-slot="4"
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

            </div>


            <!-- =====================================================
                 Widget Library
                 ===================================================== -->

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

                    </div>

                </div>

            </div>

        </main>

    </div>

</div>
