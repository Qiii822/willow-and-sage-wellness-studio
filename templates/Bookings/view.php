<?php
/**
 * One booking, opened from the calendar, with the means to record that it
 * has been paid for.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Booking $booking
 * @var array<int, array{name: string, duration: int, price_cents: int, discount_cents: int, offer_label: string|null}> $lines
 * @var array<\App\Model\Entity\Payment> $payments
 * @var \App\Model\Entity\Payment|null $paidPayment
 * @var string $customerName
 * @var int $duration
 * @var int $menuCents The treatments at full menu price.
 * @var int $discountCents What an offer took off when this was booked.
 * @var int $suggestedCents What they were quoted, pre-filled as the amount.
 * @var int $expectedCents The price the payment is compared against.
 * @var bool $isPaid
 * @var bool $isPaidInSquare Paid at the counter in Square, matched by customer and day.
 * @var bool $isUnpaid Owing - over without a payment, or marked by staff.
 * @var bool $isMarkedUnpaid Marked by staff, and so clearable.
 * @var array<string, string> $statuses
 * @var string $statusLabel What to call this booking's status on screen.
 * @var string $statusClass The badge class that goes with it.
 * @var array<string, string> $paymentMethods
 * @var bool $canTakePayment
 * @var bool $canCancel Still to come, and not already called off.
 */

$this->assign('title', 'Booking');
$this->setLayout('dashboard');

$formatMoney = fn (int $cents): string => '$' . number_format($cents / 100, 2);
$formatMinutes = function (int $minutes): string {
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;

    return trim(($hours ? $hours . ' hr ' : '') . ($rest ? $rest . ' min' : ''));
};

// Back to the week this booking is in, not whatever week is current.
$calendarLink = ['action' => 'calendar'];

if ($booking->start_at) {
    $calendarLink['?'] = ['from' => $booking->start_at->format('Y-m-d')];
}
?>

<div class="container-fluid p-0">
    <div class="dashboard-container">

        <?= $this->element('sidebar') ?>

        <main class="main-content">

            <div class="top-bar">
                <div>
                    <h1><?= h($customerName) ?></h1>
                    <p class="subtitle">
                        <?php if ($booking->start_at): ?>
                            <?= h($booking->start_at->format('l j F Y, g:i a')) ?>
                            &middot; <?= h($formatMinutes($duration)) ?>
                        <?php else: ?>
                            No time set
                        <?php endif; ?>
                    </p>
                </div>

                <div class="booking-top-actions">

                    <?php /*
                     * Up here with the appointment itself rather than down in
                     * the payment card: calling off an appointment is about
                     * the booking, not about the money, and the calendar
                     * sends people straight to this page to do it.
                     *
                     * Only while Square would take it - once the appointment
                     * has started, or it has been called off already, there
                     * is nothing to offer.
                     */ ?>
                    <?php if ($canCancel && $canTakePayment): ?>
                        <?= $this->Form->create(null, [
                            'url' => ['action' => 'cancel', $booking->id],
                            'class' => 'booking-cancel-form',
                        ]) ?>
                        <?= $this->Form->button('Cancel appointment', [
                            'class' => 'booking-link-button is-danger',
                            'confirm' => sprintf(
                                "Cancel %s's appointment%s? Square is told straight away and the slot is freed up. This cannot be undone.",
                                $customerName,
                                $booking->start_at
                                    ? ' on ' . $booking->start_at->format('j F, g:i a')
                                    : '',
                            ),
                        ]) ?>
                        <?= $this->Form->end() ?>
                    <?php endif; ?>

                    <?= $this->Html->link(
                        '&larr; Back to calendar',
                        $calendarLink,
                        ['escape' => false, 'class' => 'table-action']
                    ) ?>

                </div>
            </div>

            <div class="card table-card">
                <div class="table-header">
                    <div>
                        <h3>Booking details</h3>
                    </div>
                </div>

                <table class="dashboard-table">
                    <tbody>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="status-badge <?= h($statusClass) ?>">
                                <?= h($statusLabel) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= h($booking->customer->email_address ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?= h($booking->customer->phone_number ?? '-') ?></td>
                    </tr>
                    <?php if ($booking->customer_note): ?>
                        <tr>
                            <th>Customer note</th>
                            <td><?= nl2br(h($booking->customer_note)) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($booking->seller_note): ?>
                        <tr>
                            <th>Studio note</th>
                            <td><?= nl2br(h($booking->seller_note)) ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card table-card">
                <div class="table-header">
                    <div>
                        <h3>Treatments</h3>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                        <tr>
                            <th>Treatment</th>
                            <th>Time</th>
                            <th>Price</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($lines === []): ?>
                            <tr>
                                <td colspan="3">Square sent no treatments for this booking.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td>
                                    <?= h($line['name']) ?>

                                    <?php /*
                                     * The offer that was running when this
                                     * was booked, named on the line it
                                     * applies to - so a total lower than the
                                     * menu has its reason beside it.
                                     */ ?>
                                    <?php if (!empty($line['offer_label'])): ?>
                                        <span class="booking-offer"><?= h($line['offer_label']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($line['duration'] ? $formatMinutes($line['duration']) : '-') ?></td>
                                <td>
                                    <?php if (!$line['price_cents']): ?>
                                        -
                                    <?php elseif (!empty($line['discount_cents'])): ?>
                                        <s><?= h($formatMoney($line['price_cents'])) ?></s>
                                        <?= h($formatMoney($line['price_cents'] - $line['discount_cents'])) ?>
                                    <?php else: ?>
                                        <?= h($formatMoney($line['price_cents'])) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($lines !== [] && $discountCents > 0): ?>
                            <?php /*
                             * Spelled out rather than only showing the lower
                             * total, so the figure can be checked against the
                             * menu without opening the offers screen.
                             */ ?>
                            <tr>
                                <td colspan="2">Menu price</td>
                                <td><?= h($formatMoney($menuCents)) ?></td>
                            </tr>
                            <tr class="booking-discount">
                                <td colspan="2">Offer applied</td>
                                <td>- <?= h($formatMoney($discountCents)) ?></td>
                            </tr>
                        <?php endif; ?>

                        <?php if ($lines !== []): ?>
                            <tr class="booking-total">
                                <th colspan="2">Total</th>
                                <th><?= h($formatMoney($suggestedCents)) ?></th>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card table-card<?= $isUnpaid ? ' is-unpaid' : '' ?>">
                <div class="table-header">
                    <div>
                        <h3>Payment</h3>
                        <p>
                            <?php if ($isPaid): ?>
                                Paid
                            <?php elseif ($isPaidInSquare): ?>
                                Paid in Square - a payment from this customer on the day of the appointment
                            <?php elseif ($isUnpaid): ?>
                                <span class="unpaid-badge">Unpaid</span>
                                <?= $booking->is_finished ? 'Appointment is over, no payment recorded' : 'Marked as owing' ?>
                                <?= $expectedCents ? '&middot; ' . h($formatMoney($expectedCents)) : '' ?>
                            <?php else: ?>
                                Not paid yet
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php
                /*
                 * Said plainly when the money taken is not the menu price, so
                 * a discount or an extra charge is visible rather than only
                 * findable by adding up the treatments.
                 */
                $difference = $paidPayment && $expectedCents
                    ? $paidPayment->amount - $expectedCents
                    : 0;
                ?>
                <?php if ($difference !== 0): ?>
                    <p class="price-difference">
                        <strong>Charged <?= $difference < 0 ? 'less' : 'more' ?> than the menu price.</strong>
                        Menu price <?= h($formatMoney($expectedCents)) ?>,
                        received <?= h($formatMoney($paidPayment->amount)) ?>
                        (<?= $difference < 0 ? '-' : '+' ?><?= h($formatMoney(abs($difference))) ?>).
                    </p>
                <?php endif; ?>

                <?php if ($payments !== []): ?>
                    <div class="table-responsive">
                        <table class="dashboard-table">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Method</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <?= $payment->square_created_at
                                            ? h($payment->square_created_at->format('d/m/Y H:i'))
                                            : '-' ?>
                                    </td>
                                    <td><?= h($formatMoney($payment->amount)) ?></td>
                                    <td>
                                        <?php if ($payment->is_undone): ?>
                                            <span class="status-badge undone">Refunded</span>
                                        <?php else: ?>
                                            <span class="status-badge <?= h($payment->status) ?>">
                                                <?= h(ucfirst($payment->status)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($payment->source_type === 'EXTERNAL' ? 'Card (studio card reader)' : ucfirst(strtolower((string)$payment->source_type))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!$isPaid && !$isPaidInSquare && $canTakePayment): ?>

                    <?php /*
                     * Recorded once the customer has paid at the studio. The
                     * amount starts at the menu price but can be changed, for
                     * a discount or a treatment added on the day.
                     */ ?>
                    <?= $this->Form->create(null, [
                        'url' => ['action' => 'pay', $booking->id],
                        'class' => 'booking-pay-form',
                    ]) ?>

                    <div class="filter-field">
                        <label for="pay-amount">Amount (AUD)</label>
                        <input
                            type="text"
                            inputmode="decimal"
                            name="amount"
                            id="pay-amount"
                            required
                            value="<?= h($suggestedCents ? number_format($suggestedCents / 100, 2, '.', '') : '') ?>"
                        >
                    </div>

                    <div class="filter-field">
                        <label for="pay-method">Paid by</label>
                        <select name="method" id="pay-method" required>
                            <?php foreach ($paymentMethods as $value => $label): ?>
                                <option value="<?= h($value) ?>"><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-field booking-pay-note">
                        <label for="pay-note">Note (optional)</label>
                        <input type="text" name="note" id="pay-note" maxlength="500">
                    </div>

                    <?= $this->Form->button('Complete payment', [
                        'class' => 'table-action',
                        'confirm' => 'Record this payment in Square? It shows on the payments page straight away.',
                    ]) ?>

                    <?= $this->Form->end() ?>

                    <?php /*
                     * Its own form, beside the payment one rather than in it,
                     * so marking a booking as owing never sends an amount.
                     *
                     * Not offered once the appointment is over: it is owing
                     * by then anyway, and only a payment clears it.
                     */ ?>
                    <?php if (!$booking->is_finished): ?>
                        <?= $this->Form->create(null, [
                            'url' => ['action' => 'unpaid', $booking->id],
                            'class' => 'booking-secondary-action',
                        ]) ?>
                        <?= $this->Form->hidden('unpaid', ['value' => $isMarkedUnpaid ? 0 : 1]) ?>
                        <?= $this->Form->button($isMarkedUnpaid ? 'Clear unpaid mark' : 'Mark as unpaid', [
                            'class' => 'booking-link-button' . ($isMarkedUnpaid ? '' : ' is-danger'),
                        ]) ?>
                        <span class="booking-pay-help-inline">
                            <?= $isMarkedUnpaid
                                ? 'Recording a payment clears it too.'
                                : 'For a customer who left without paying - it shows in red on the calendar and payments page.' ?>
                        </span>
                        <?= $this->Form->end() ?>
                    <?php endif; ?>

                <?php elseif ($isPaid && $canTakePayment): ?>

                    <?php /*
                     * For a payment recorded by mistake - the wrong booking, or
                     * before the customer had actually paid. Square refunds it
                     * in full, since it will not delete a completed payment.
                     */ ?>
                    <?= $this->Form->create(null, [
                        'url' => ['action' => 'undo', $booking->id],
                        'class' => 'booking-secondary-action',
                    ]) ?>
                    <?= $this->Form->button('Refund payment', [
                        'class' => 'booking-link-button is-danger',
                        'confirm' => sprintf(
                            'Refund the %s payment? Square records it as refunded in full, and the booking goes back to not paid.',
                            $formatMoney($paidPayment->amount),
                        ),
                    ]) ?>
                    <span class="booking-pay-help-inline">
                        <?= $this->Html->link('See it on the payments page', ['controller' => 'Payments', 'action' => 'index']) ?>
                    </span>
                    <?= $this->Form->end() ?>

                <?php elseif (!$isPaid && !$isPaidInSquare): ?>
                    <p class="booking-pay-help">An admin can record the payment once the customer has paid.</p>
                <?php else: ?>
                    <p class="booking-pay-help">
                        <?= $this->Html->link('See it on the payments page', ['controller' => 'Payments', 'action' => 'index']) ?>
                    </p>
                <?php endif; ?>

            </div>

        </main>

    </div>
</div>
