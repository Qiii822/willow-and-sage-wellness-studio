<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Customer $customer
 * @var iterable $upcomingBookings
 */

$this->assign('title', 'Customer');
$this->setLayout('dashboard');

$statusLabel = fn (string $status): string => ucwords(str_replace('_', ' ', $status));
$statusClass = fn (string $status): string => str_replace('_', '-', $status);

$customerName = trim(
    ($customer->given_name ?? '') . ' ' .
    ($customer->family_name ?? '')
) ?: '(No name)';
?>

<div class="container-fluid p-0">
    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">
            <div class="top-bar">
                <div>
                    <?= $this->Html->link(
                        '&larr; Back to Customers',
                        [
                            'controller' => 'Customers',
                            'action' => 'index',
                        ],
                        [
                            'escape' => false,
                            'class' => 'table-action',
                        ]
                    ) ?>
                    <h1><?= h($customerName) ?></h1>
                    <p class="subtitle">Customer details and upcoming bookings.</p>
                </div>
            </div>

            <div class="card table-card">
                <div class="table-header">
                    <div>
                        <h3>Customer Details</h3>
                    </div>
                </div>

                <table class="dashboard-table">
                    <tbody>
                    <tr>
                        <th>Email</th>
                        <td><?= h($customer->email_address ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?= h($customer->phone_number ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Account Created</th>
                        <td>
                            <?php if ($customer->square_created_at): ?>
                                <?= h($customer->square_created_at->format('d/m/Y H:i:s')) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="card table-card">
                <div class="table-header">
                    <div>
                        <h3>Upcoming Bookings</h3>
                        <p><?= count($upcomingBookings) ?> upcoming</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                        <tr>
                            <th>Start</th>
                            <th>Status</th>
                            <th>Location</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php if (!count($upcomingBookings)): ?>
                            <tr>
                                <td colspan="3">No upcoming bookings.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($upcomingBookings as $booking): ?>
                            <tr>
                                <td>
                                    <?php if ($booking->start_at): ?>
                                        <?= h($booking->start_at->format('d/m/Y H:i:s')) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= h($statusClass($booking->status)) ?>">
                                        <?= h($statusLabel($booking->status)) ?>
                                    </span>
                                </td>
                                <td><?= h($booking->location_id ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

    </div>
</div>
