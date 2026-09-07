<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\I18n\DateTime;
use Cake\ORM\Table;

/**
 * One-time codes proving a customer owns a phone number.
 */
class BookingAccessCodesTable extends Table
{
    /** Long enough to be typed from a text, short enough to expire unused. */
    public const LIFETIME_MINUTES = 10;

    /** After this many wrong guesses the code is dead, not just wrong. */
    public const MAX_ATTEMPTS = 5;

    /** Stops the send button being used to text someone repeatedly. */
    public const RESEND_SECONDS = 60;

    /**
     * @param array<string, mixed> $config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('booking_access_codes');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'modified' => 'always',
                ],
            ],
        ]);
    }

    /**
     * The comparable form of a phone number.
     *
     * People write the same number as "0412 345 678", "+61 412 345 678" or
     * "0412345678". Reduced to digits and then to the last nine, all three
     * land on the same value - nine because that is the Australian number
     * without either the leading zero or the country code.
     *
     * @param string|null $phone
     * @return string
     */
    public static function digits(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string)$phone) ?? '';

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }

    /**
     * Issues a code for this number, unless one was just sent.
     *
     * @param string $phone
     * @return array{code: string|null, wait: int} The code to deliver, or
     *  null with the seconds left before another may be asked for.
     */
    public function issue(string $phone): array
    {
        $digits = self::digits($phone);

        /*
         * Deliberately not limited to unused codes: the throttle is about
         * how often a number can be texted, and a code being spent does not
         * make the next text less of a nuisance to whoever owns the phone.
         */
        $recent = $this->find()
            ->where([
                'phone_digits' => $digits,
                'created_at >' => DateTime::now()->subSeconds(self::RESEND_SECONDS),
            ])
            ->orderByDesc('created_at')
            ->first();

        if ($recent !== null) {
            $wait = self::RESEND_SECONDS - (DateTime::now()->getTimestamp() - $recent->created_at->getTimestamp());

            return ['code' => null, 'wait' => max(1, $wait)];
        }

        // Six digits, zero padded, from a source suitable for secrets.
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $entity = $this->newEmptyEntity();
        $entity->phone_digits = $digits;
        $entity->code_hash = password_hash($code, PASSWORD_DEFAULT);
        $entity->attempts = 0;
        $entity->expires_at = DateTime::now()->addMinutes(self::LIFETIME_MINUTES);

        $this->saveOrFail($entity);

        return ['code' => $code, 'wait' => 0];
    }

    /**
     * Checks a code against the newest live one for this number.
     *
     * A wrong guess is counted whether or not the code was close, and the
     * code dies once used, so one text is good for one sign-in.
     *
     * @param string $phone
     * @param string $code
     * @return bool
     */
    public function verify(string $phone, string $code): bool
    {
        $digits = self::digits($phone);

        $row = $this->find()
            ->where([
                'phone_digits' => $digits,
                'used_at IS' => null,
                'expires_at >' => DateTime::now(),
                'attempts <' => self::MAX_ATTEMPTS,
            ])
            ->orderByDesc('created_at')
            ->first();

        if ($row === null) {
            return false;
        }

        $row->attempts = (int)$row->attempts + 1;

        if (!password_verify(trim($code), $row->code_hash)) {
            $this->save($row);

            return false;
        }

        $row->used_at = DateTime::now();
        $this->save($row);

        return true;
    }

    /**
     * Clears codes that are spent or expired.
     *
     * @return int
     */
    public function purge(): int
    {
        return $this->deleteAll([
            'OR' => [
                'expires_at <' => DateTime::now()->subDays(1),
                'used_at IS NOT' => null,
            ],
        ]);
    }
}
