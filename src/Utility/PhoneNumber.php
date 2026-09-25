<?php
declare(strict_types=1);

namespace App\Utility;

/**
 * Australian mobile numbers, reduced to one canonical spelling.
 *
 * The same phone reaches this app written several ways: a customer types
 * `0415 555 365` on the signup form, while Square returns the very same
 * number as `+61 415 555 365`. Comparing or uniquely indexing the raw text
 * would treat those as two different people, so everything is stored and
 * matched in E.164 (`+61415555365`).
 */
class PhoneNumber
{
    /**
     * Converts an Australian mobile number to E.164.
     *
     * @param string|null $raw Whatever the user or Square supplied.
     * @return string|null The number as `+61XXXXXXXXX`, or null if it is not
     *   a recognisable Australian mobile.
     */
    public static function normalise(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        // Keep only digits; a leading + is implied by the country code rules
        // below, and separators vary too much to be worth preserving.
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        // 0011/0061 international prefixes, then the plain country code.
        if (str_starts_with($digits, '0061')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '61') && strlen($digits) === 11) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            // Domestic form: 04XX XXX XXX
            $digits = substr($digits, 1);
        }

        // What remains must be the 9-digit subscriber number of a mobile,
        // which in Australia always begins with 4.
        if (strlen($digits) !== 9 || $digits[0] !== '4') {
            return null;
        }

        return '+61' . $digits;
    }

    /**
     * True when the value is a usable Australian mobile number.
     *
     * @param string|null $raw
     * @return bool
     */
    public static function isValid(?string $raw): bool
    {
        return self::normalise($raw) !== null;
    }

    /**
     * Renders a stored E.164 number the way it is normally written here.
     *
     * @param string|null $e164 A number produced by normalise().
     * @return string|null `+61 4XX XXX XXX`, or the input unchanged if it is
     *   not in the expected shape.
     */
    public static function format(?string $e164): ?string
    {
        if ($e164 === null || !preg_match('/^\+61(\d{3})(\d{3})(\d{3})$/', $e164, $m)) {
            return $e164;
        }

        return '+61 ' . $m[1] . ' ' . $m[2] . ' ' . $m[3];
    }
}
