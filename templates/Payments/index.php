<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $payments
 * @var array<string, string> $statuses
 * @var string|null $status
 * @var string|null $startDate
 * @var string|null $endDate
 */

$this->assign('title', 'Payments');
$this->setLayout('dashboard');

$hasActiveFilters = $status || $startDate || $endDate;

// Amounts are integer minor units (e.g. cents) - format as major currency
// units for display, e.g. 555 -> "$5.55". Currency is always AUD.
$formatMoney = fn (int $amount): string => '$' . number_format($amount / 100, 2);
?>

<div class="container-fluid p-0">
    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">
            <div class="top-bar">
                <div>
                    <h1>Payments</h1>
                    <p class="subtitle">All payments synced from Square.</p>
                </div>
            </div>

            <div class="card table-card">
                <div class="table-header">
                    <div>
                        <h3>All Payments</h3>
                        <p><?= count($payments) ?> payments</p>
                    </div>
                </div>

                <form
                        class="payments-filter-form"
                        method="get"
                        action="<?= $this->Url->build([
                            'controller' => 'Payments',
                            'action' => 'index',
                        ]) ?>"
                >
                    <div class="filter-field">
                        <label for="payment-status">Status</label>
                        <select
                                name="status"
                                id="payment-status"
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
                        <label for="payment-start-date">From</label>
                        <input
                                type="date"
                                name="start_date"
                                id="payment-start-date"
                                value="<?= h($startDate ?? '') ?>"
                        >
                    </div>

                    <div class="filter-field">
                        <label for="payment-end-date">To</label>
                        <input
                                type="date"
                                name="end_date"
                                id="payment-end-date"
                                value="<?= h($endDate ?? '') ?>"
                        >
                    </div>

                    <button class="table-action" type="submit">Filter</button>

                    <?php if ($hasActiveFilters): ?>
                        <a
                                class="filter-clear"
                                href="<?= $this->Url->build([
                                    'controller' => 'Payments',
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
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>Receipt</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php if (count($payments) === 0): ?>
                            <tr>
                                <td colspan="6">
                                    No payments match the selected filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <?php if ($payment->square_created_at): ?>
                                        <?= h($payment->square_created_at->format('d/m/Y H:i:s')) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($payment->customer): ?>
                                        <?= h(trim(
                                            ($payment->customer->given_name ?? '') . ' ' .
                                            ($payment->customer->family_name ?? '')
                                        )) ?: '—' ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= h($formatMoney($payment->amount)) ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= h($payment->status) ?>">
                                        <?= h($statuses[$payment->status] ?? ucfirst($payment->status)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= h($payment->source_type ?? '-') ?>
                                </td>
                                <td>
                                    <?php if ($payment->receipt_url): ?>
                                        <?= $this->Html->link(
                                            $payment->receipt_number ?? 'View',
                                            $payment->receipt_url,
                                            ['target' => '_blank', 'rel' => 'noopener']
                                        ) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

    </div>
</div>
