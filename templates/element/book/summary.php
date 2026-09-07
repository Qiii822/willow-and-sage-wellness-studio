<?php
/**
 * What the customer has chosen so far.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\BookingDraft $draft
 * @var string|null $timezone Shown only once a time has been picked.
 */

$timezone = $timezone ?? null;

$money = static function (?int $cents): string {
    return $cents === null ? 'Price varies' : 'AU$' . number_format($cents / 100, 2);
};
?>

<aside class="book-summary">

    <h2>Your booking</h2>

    <ul class="book-summary-items">
        <?php foreach ($draft->items as $item): ?>
            <li>
                <span class="book-summary-name"><?= h($item['name']) ?></span>
                <span class="book-summary-price"><?= h($money($item['price_cents'] ?? null)) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($timezone !== null && $draft->start_at !== null): ?>
        <p class="book-summary-when">
            <?= h($draft->startAtLocal($timezone)) ?>
        </p>
    <?php endif; ?>

    <?php if ((int)$draft->discount_cents > 0): ?>
        <p class="book-summary-line">
            <span>Subtotal</span>
            <span><?= h($money($draft->subtotal_cents)) ?></span>
        </p>
        <p class="book-summary-line is-discount">
            <span><?= h($draft->discount_code ?? 'Discount') ?></span>
            <span>- <?= h($money($draft->discount_cents)) ?></span>
        </p>
    <?php endif; ?>

    <p class="book-summary-total">
        <span>Total</span>
        <span><?= h($money($draft->payable_cents)) ?></span>
    </p>

    <?php /*
     * Said here rather than only at the end: someone deciding whether to
     * book should know they are not being asked for card details.
     */ ?>
    <p class="book-summary-note">
        Nothing to pay now - you settle up at the studio after your treatment.
    </p>

</aside>
