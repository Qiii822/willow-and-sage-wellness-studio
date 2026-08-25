<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $bookings
 * @var array<string, string> $statuses
 * @var string|null $status
 * @var string|null $startDate
 * @var string|null $endDate
 */

$this->assign('title', 'Bookings');
$this->setLayout('dashboard');

$hasActiveFilters = $status || $startDate || $endDate;

// Square's enum values (e.g. "cancelled_by_customer") map to a
// "Cancelled By Customer" label and a "cancelled-by-customer" badge class.
$statusLabel = fn (string $status): string => ucwords(str_replace('_', ' ', $status));
$statusClass = fn (string $status): string => str_replace('_', '-', $status);
?>

<div class="container-fluid p-0">
    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">
            <div class="top-bar">
                <div>
                    <h1>Bookings</h1>
                    <p class="subtitle">All bookings synced from Square.</p>
                </div>
            </div>

            <div class="card table-card">
                <div class="table-header">
                    <div>
                        <h3>All Bookings</h3>
                        <p><?= count($bookings) ?> bookings</p>
                    </div>
                </div>

                <form
                        class="bookings-filter-form"
                        method="get"
                        action="<?= $this->Url->build([
                            'controller' => 'Bookings',
                            'action' => 'index',
                        ]) ?>"
                >
                    <div class="filter-field">
                        <label for="booking-status">Status</label>
                        <select
                                name="status"
                                id="booking-status"
                                onchange="this.form.submit()"
                        >
                            <option value="">All statuses</option>
                            <?php foreach ($statuses as $value => $label): ?>
                                <option
                                        value="<?= h($value) ?>"
                                        <?= $status === $value ? 'selected' : '' ?>
                                >
                                    <?= h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="booking-start-date">From</label>
                        <input
                                type="date"
                                name="start_date"
                                id="booking-start-date"
                                value="<?= h($startDate ?? '') ?>"
                        >
                    </div>

                    <div class="filter-field">
                        <label for="booking-end-date">To</label>
                        <input
                                type="date"
                                name="end_date"
                                id="booking-end-date"
                                value="<?= h($endDate ?? '') ?>"
                        >
                    </div>

                    <button class="table-action" type="submit">Filter</button>

                    <?php if ($hasActiveFilters): ?>
                        <a
                                class="filter-clear"
                                href="<?= $this->Url->build([
                                    'controller' => 'Bookings',
                                    'action' => 'index',
                                ]) ?>"
                        >
                            Clear filters
                        </a>
                    <?php endif; ?>
                </form>

                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Start</th>
                            <th>Status</th>
                            <th>Location</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php if (count($bookings) === 0): ?>
                            <tr>
                                <td colspan="4">
                                    No bookings match the selected filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <?php if ($booking->customer): ?>
                                        <?= h(trim(
                                            ($booking->customer->given_name ?? '') . ' ' .
                                            ($booking->customer->family_name ?? '')
                                        )) ?: '—' ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
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
