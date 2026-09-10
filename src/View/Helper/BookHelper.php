<?php
declare(strict_types=1);

namespace App\View\Helper;

use App\Model\Duration;
use Cake\View\Helper;

/**
 * Small formatting the booking pages need in more than one place.
 */
class BookHelper extends Helper
{
    /**
     * Minutes as the studio would say them: "1 hr 15 min", not "75".
     *
     * The same wording a treatment carries on the menu, so a total reads
     * as the sum of the parts the customer was just looking at.
     *
     * @param int|null $minutes
     * @return string
     */
    public function duration(?int $minutes): string
    {
        return Duration::label($minutes);
    }
}
