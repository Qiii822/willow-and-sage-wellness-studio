<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * One current offer.
 *
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property string|null $terms
 * @property string|null $image
 * @property int|null $price_before_cents
 * @property int|null $price_after_cents
 * @property int|null $discount_percent
 * @property int|null $discount_amount_cents
 * @property string|null $square_catalog_id
 * @property string|null $service_id
 * @property \App\Model\Entity\Service|null $service
 * @property int $position
 * @property bool $visible
 */
class Offer extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'description' => true,
        'terms' => true,
        'price_before_cents' => true,
        'price_after_cents' => true,
        'service_id' => true,
        'position' => true,
        'visible' => true,
        /*
         * Square's, and written by the sync alone. The edit form shows both
         * read-only; marking them fillable would let a crafted post change
         * what the next sync is only going to put back.
         */
        'title' => false,
        'discount_percent' => false,
        /*
         * Set only by the controller once the upload service has accepted a
         * file and named it. Marking it fillable would let a submitted form
         * point the page at any file it liked.
         */
        'image' => false,
        /*
         * Written by the sync alone. Both describe the Square discount this
         * offer mirrors, and a form that could set them could claim an
         * offer was Square's, or point two offers at one discount.
         */
        'square_catalog_id' => false,
        'discount_amount_cents' => false,
    ];

    /**
     * The badge, e.g. "25% off" or "$20 off".
     *
     * Worked out from the two prices whenever both are set, so the badge can
     * never disagree with the numbers printed beside it. The stored
     * percentage is only used for an offer with no specific before and after
     * price - a flat "20% off facials".
     *
     * A fixed amount is the other shape a Square discount comes in, and is
     * only reached when there is no percentage to prefer: a percentage reads
     * better on a card, and when both are somehow set the two would be
     * saying the same thing twice.
     *
     * @return string|null Null when there is no discount to announce.
     */
    protected function _getDiscountLabel(): ?string
    {
        $percent = $this->discount_percent;

        $before = $this->before_cents;
        $after = $this->after_cents;

        if ($before !== null && $after !== null && $before > 0 && $after < $before) {
            $percent = (int)round(($before - $after) / $before * 100);
        }

        if ($percent !== null && $percent > 0) {
            return $percent . '% off';
        }

        $amount = $this->discount_amount_cents;

        return $amount !== null && $amount > 0
            ? $this->money($amount) . ' off'
            : null;
    }

    /*
     * An is_from_square flag was here, for the screens to tell a synced offer
     * from a hand-written one. Every offer comes from Square now - there is
     * no Add, and the sync removes anything without a Square id - so it
     * answered true for every row and nothing was left to branch on.
     */

    /**
     * The usual price, in cents.
     *
     * Taken from the treatment the offer is about, so "10% off the Willow
     * Ritual" prices itself from the Willow Ritual and keeps up when that
     * price changes in Square. A figure typed into the offer wins, for the
     * offer that is not simply a treatment at a discount - a package, or a
     * bundle of two.
     *
     * @return int|null
     */
    protected function _getBeforeCents(): ?int
    {
        return $this->price_before_cents ?? $this->linkedService()?->price_cents;
    }

    /**
     * What is actually being charged, in cents.
     *
     * Worked out by taking the discount off the usual price, so the two can
     * never disagree: the badge says 10% off and the figure beside it is
     * 10% off. A typed figure wins here too.
     *
     * @return int|null
     */
    protected function _getAfterCents(): ?int
    {
        if ($this->price_after_cents !== null) {
            return $this->price_after_cents;
        }

        $before = $this->before_cents;

        if ($before === null) {
            return null;
        }

        if ($this->discount_percent !== null && $this->discount_percent > 0) {
            return (int)round($before * (100 - $this->discount_percent) / 100);
        }

        if ($this->discount_amount_cents !== null && $this->discount_amount_cents > 0) {
            // Never below nothing, however large the discount in Square is.
            return max(0, $before - $this->discount_amount_cents);
        }

        return null;
    }

    /**
     * The price before the offer, e.g. "AU$170.00".
     *
     * @return string|null
     */
    protected function _getPriceBeforeLabel(): ?string
    {
        return $this->money($this->before_cents);
    }

    /**
     * The price being charged during the offer.
     *
     * @return string|null
     */
    protected function _getPriceAfterLabel(): ?string
    {
        return $this->money($this->after_cents);
    }

    /**
     * Whether there is a genuine saving to strike through.
     *
     * A "was" price on its own, or one no higher than the "now" price, is not
     * a saving and is not shown as one.
     *
     * @return bool
     */
    protected function _getHasSaving(): bool
    {
        $before = $this->before_cents;
        $after = $this->after_cents;

        return $before !== null && $after !== null && $before > $after;
    }

    /**
     * The treatment this offer is about, when one is loaded and still here.
     *
     * Guarded on both counts: the association is only present on a query
     * that asked for it, and the catalogue sync empties the services table,
     * so an offer can point at a treatment Square no longer has. Both read
     * as "no treatment", which is also what a general offer looks like.
     *
     * @return \App\Model\Entity\Service|null
     */
    protected function linkedService(): ?Service
    {
        return $this->has('service') ? $this->service : null;
    }

    /**
     * @param int|null $cents
     * @return string|null
     */
    protected function money(?int $cents): ?string
    {
        return $cents === null
            ? null
            : 'AU$' . number_format($cents / 100, 2);
    }
}
