<?php
declare(strict_types=1);

namespace App\Model;

/**
 * How long something takes, said the way the studio says it.
 *
 * One place, because three had grown up: a treatment's own label on the
 * menu, the total in the booking pages, and the controller checking a cart
 * against the opening hours. They have to agree - a total that reads in a
 * different format from the parts it sums looks like a different number.
 */
class Duration
{
    /**
     * "1 hr 15 min", "45 min", "2 hr".
     *
     * @param int|null $minutes
     * @return string
     */
    public static function label(?int $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return '-';
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
