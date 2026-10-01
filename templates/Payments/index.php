<?php
/**
 * @var \App\View\AppView $this
 * @var array<int, array<string, mixed>> $rows Payments and unpaid bookings, newest first.
 * @var array<string, array{id: string, expected: int|null}> $linked The booking behind
 *  each row, keyed by Square booking id - payments taken in Square itself have none.
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

$customerName = function ($customer): string {
    if (!$customer) {
        return '—';
    }

    return trim(($customer->given_name ?? '') . ' ' . ($customer->family_name ?? '')) ?: '—';
};

$unpaidCount = count(array_filter($rows, fn (array $row): bool => $row['type'] === 'unpaid'));
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
                        <p>
                            <?= count($rows) - $unpaidCount ?> payments
                            <?php if ($unpaidCount > 0): ?>
                                &middot; <span class="unpaid-text"><?= $unpaidCount ?> unpaid</span>
                            <?php endif; ?>
                        </p>
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
                        <?php if ($rows === []): ?>
                            <tr>
                                <td colspan="6">
                                    No payments match the selected filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $row): ?>

                            <?php if ($row['type'] === 'unpaid'): ?>
                                <?php
                                /*
                                 * A booking marked as owing has no payment to
                                 * show, so it is its own row - in red, dated by
                                 * the appointment - until it is paid for.
                                 */
                                $booking = $row['booking'];
                                $owed = $linked[$booking->square_booking_id]['expected'] ?? null;
                                $href = $this->Url->build(['controller' => 'Bookings', 'action' => 'view', $booking->id]);
                                ?>
                                <tr class="is-unpaid is-clickable" data-href="<?= h($href) ?>">
                                    <td>
                                        <?= $booking->start_at ? h($booking->start_at->format('d/m/Y')) : '-' ?>
                                    </td>
                                    <td><a href="<?= h($href) ?>"><?= h($customerName($booking->customer)) ?></a></td>
                                    <td><?= $owed ? h($formatMoney($owed)) : '-' ?></td>
                                    <td><span class="status-badge unpaid">Unpaid</span></td>
                                    <td>-</td>
                                    <td>
                                        <?= $this->Html->link('View booking', [
                                            'controller' => 'Bookings',
                                            'action' => 'view',
                                            $booking->id,
                                        ]) ?>
                                    </td>
                                </tr>
                                <?php continue; ?>
                            <?php endif; ?>

                            <?php
                            $payment = $row['payment'];
                            $link = $linked[(string)$payment->reference_id] ?? null;
                            $expected = $link['expected'] ?? null;
                            $difference = $expected && !$payment->is_undone ? $payment->amount - $expected : 0;
                            $href = $link
                                ? $this->Url->build(['controller' => 'Bookings', 'action' => 'view', $link['id']])
                                : null;

                            $classes = array_filter([
                                $payment->is_undone ? 'is-undone' : null,
                                $href ? 'is-clickable' : null,
                            ]);
                            ?>
                            <tr<?= $classes ? ' class="' . implode(' ', $classes) . '"' : '' ?><?= $href ? ' data-href="' . h($href) . '"' : '' ?>>
                                <td>
                                    <?php if ($payment->square_created_at): ?>
                                        <?= h($payment->square_created_at->format('d/m/Y')) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php // A real link too, for the keyboard and for opening in a new tab. ?>
                                    <?php if ($href): ?>
                                        <a href="<?= h($href) ?>"><?= h($customerName($payment->customer)) ?></a>
                                    <?php else: ?>
                                        <?= h($customerName($payment->customer)) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= h($formatMoney($payment->amount)) ?>
                                    <?php /*
                                     * The same warning the booking page gives,
                                     * so a discount or an extra charge shows up
                                     * without opening the booking.
                                     */ ?>
                                    <?php if ($difference !== 0): ?>
                                        <span class="price-adjusted">
                                            <?= $difference < 0 ? 'Less' : 'More' ?> than menu
                                            <?= h($formatMoney($expected)) ?>
                                            (<?= $difference < 0 ? '-' : '+' ?><?= h($formatMoney(abs($difference))) ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php /*
                                     * Payments undone from a booking are not
                                     * listed here at all; one fully refunded
                                     * in Square itself still is.
                                     */ ?>
                                    <?php if ($payment->is_undone): ?>
                                        <span class="status-badge undone">Refunded</span>
                                    <?php else: ?>
                                        <span class="status-badge <?= h($payment->status) ?>">
                                            <?= h($statuses[$payment->status] ?? ucfirst($payment->status)) ?>
                                        </span>
                                    <?php endif; ?>
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

<script>
    /*
     * A click anywhere on a booking's row opens it, not just on the name.
     * Links inside the row - the receipt, the name itself - keep doing what
     * they do, and a drag to select text is not taken for a click.
     */
    document.querySelectorAll('tr[data-href]').forEach(function (row) {
        row.addEventListener('click', function (event) {
            if (event.target.closest('a, button') || window.getSelection().toString() !== '') {
                return;
            }

            window.location.href = row.dataset.href;
        });
    });
</script>
