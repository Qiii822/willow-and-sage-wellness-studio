<?php
/** @var \App\View\AppView $this */

$this->assign('title', 'Dashboard');
$this->setLayout('dashboard');
?>

<div class="container-fluid p-0">

    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">

            <?= $this->element('topbar') ?>




            <!-- Dashboard Grid -->
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
                                View customers added on the selected date.
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
