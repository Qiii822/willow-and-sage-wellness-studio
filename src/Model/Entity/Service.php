<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * A single treatment on the studio's menu.
 *
 * @property string $id
 * @property string $category
 * @property string $name
 * @property string|null $description
 * @property int|null $price_cents
 * @property string $currency
 * @property int|null $duration_minutes
 * @property int $position
 * @property bool $visible
 * @property string|null $square_catalog_id
 */
class Service extends Entity
{
    protected array $_accessible = [
        'category' => true,
        'name' => true,
        'description' => true,
        'price_cents' => true,
        'currency' => true,
        'duration_minutes' => true,
        'position' => true,
        'visible' => true,
        // Written by a future catalogue sync, never by a submitted form.
        'square_catalog_id' => false,
    ];

    /**
     * The price as the site shows it, e.g. "AU$170.00".
     *
     * @return string|null Null when no price is set, so the template can
     *   leave the line out rather than print a misleading zero.
     */
    protected function _getPriceLabel(): ?string
    {
        if ($this->price_cents === null) {
            return null;
        }

        $symbol = $this->currency === 'AUD' ? 'AU$' : $this->currency . ' ';

        return $symbol . number_format($this->price_cents / 100, 2);
    }

    /**
     * The duration in the studio's own phrasing, e.g. "2 hr 15 min".
     *
     * @return string|null
     */
    protected function _getDurationLabel(): ?string
    {
        $minutes = $this->duration_minutes;

        if ($minutes === null || $minutes <= 0) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($hours === 0) {
            return $remainder . ' min';
        }

        return $remainder === 0
            ? $hours . ' hr'
            : $hours . ' hr ' . $remainder . ' min';
    }
}
