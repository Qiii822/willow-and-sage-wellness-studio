<?php
declare(strict_types=1);

namespace App\Model;

/**
 * Australian mobile numbers, as customers actually type them.
 *
 * The studio reaches people by text, so this is the one number that has to
 * be right. Square refuses a booking outright when the customer's number is
 * not a real one - and refuses it at the point of creating the customer,
 * where the error says nothing about phones unless you go looking. Catching
 * it on the form is the difference between "check this number" and a dead
 * end at the last step.
 */
class AustralianMobile
{
    /** Digits in a mobile once the country code and leading zero are gone. */
    protected const SUBSCRIBER_DIGITS = 9;

    /**
     * The number as Square and an SMS gateway both want it: +61 then nine
     * digits, or null when it is not a mobile at all.
     *
     * Accepts the three ways people write the same number - 0412 345 678,
     * +61 412 345 678, 61 412 345 678 - along with any spaces, brackets,
     * dashes or dots they put in.
     *
     * @param string|null $value
     * @return string|null
     */
    public static function normalise(?string $value): ?string
    {
        $trimmed = trim((string)$value);
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        // A leading + only means anything against the country code, so it is
        // read here rather than thrown away with the other punctuation.
        $hasPlus = str_starts_with($trimmed, '+');

        if (str_starts_with($digits, '61')) {
            $national = substr($digits, 2);
        } elseif (!$hasPlus && str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } else {
            /*
             * No country code and no leading zero. Nine digits starting with
             * 4 is still unambiguously a mobile written bare, which people do;
             * anything else is not something to guess at.
             */
            $national = $digits;
        }

        if (strlen($national) !== self::SUBSCRIBER_DIGITS || !str_starts_with($national, '4')) {
            return null;
        }

        return '+61' . $national;
    }

    /**
     * @param string|null $value
     * @return bool
     */
    public static function isValid(?string $value): bool
    {
        return self::normalise($value) !== null;
    }
}
