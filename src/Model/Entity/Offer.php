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
 * @property int $position
 * @property bool $visible
 */
class Offer extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'title' => true,
        'description' => true,
        'terms' => true,
        'price_before_cents' => true,
        'price_after_cents' => true,
        'discount_percent' => true,
        'position' => true,
        'visible' => true,
        /*
         * Set only by the controller once the upload service has accepted a
         * file and named it. Marking it fillable would let a submitted form
         * point the page at any file it liked.
         */
        'image' => false,
    ];

    /**
     * The badge, e.g. "25% off".
     *
     * Worked out from the two prices whenever both are set, so the badge can
     * never disagree with the numbers printed beside it. The stored
     * percentage is only used for an offer with no specific before and after
     * price - a flat "20% off facials".
     *
     * @return string|null Null when there is no discount to announce.
     */
    protected function _getDiscountLabel(): ?string
    {
        $percent = $this->discount_percent;

        $before = $this->price_before_cents;
        $after = $this->price_after_cents;

        if ($before !== null && $after !== null && $before > 0 && $after < $before) {
            $percent = (int)round(($before - $after) / $before * 100);
        }

        return $percent !== null && $percent > 0
            ? $percent . '% off'
            : null;
    }

    /**
     * The price before the offer, e.g. "AU$170.00".
     *
     * @return string|null
     */
    protected function _getPriceBeforeLabel(): ?string
    {
        return $this->money($this->price_before_cents);
    }

    /**
     * The price being charged during the offer.
     *
     * @return string|null
     */
    protected function _getPriceAfterLabel(): ?string
    {
        return $this->money($this->price_after_cents);
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
        return $this->price_before_cents !== null
            && $this->price_after_cents !== null
            && $this->price_before_cents > $this->price_after_cents;
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
