<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * Gets a sign-in code to the customer.
 *
 * A placeholder with one job: be the only place that has to change when
 * the SMS work lands. Until then the code goes to the log, so the flow
 * around it can be built and used without waiting.
 *
 * In development the code is also handed back to the caller, so the page
 * can show it and the flow can be walked without a phone. That only
 * happens with debug on - in production the log is the only copy.
 */
class BookingNotifier
{
    /**
     * @param string $phone Where it should go, as the customer typed it.
     * @param string $code The six digits.
     * @return string|null The code, when it is safe to display it.
     */
    public function sendAccessCode(string $phone, string $code): ?string
    {
        // Not the code: a log line is a poor place for a working key, and
        // this one names who it was for.
        Log::info(sprintf(
            'Booking access code issued for a number ending %s',
            substr(preg_replace('/\D/', '', $phone) ?? '', -3),
        ));

        /*
         * TODO: hand off to the SMS service when it exists. Everything
         * else in the flow already works; this is the only gap.
         */
        Log::debug('Booking access code (development only): ' . $code);

        return Configure::read('debug') ? $code : null;
    }
}
