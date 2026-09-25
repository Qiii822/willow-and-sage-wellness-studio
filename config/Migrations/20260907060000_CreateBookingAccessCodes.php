<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * One-time codes that let a customer see their own bookings.
 *
 * There are no accounts on the public site, so the phone number is the
 * only thing a customer reliably has. A number alone cannot be the key -
 * anyone who knows it could read someone's appointments and cancel them -
 * so it has to be proved by a code sent to that number.
 *
 * The code is stored hashed and counted, because this row is the whole of
 * the protection around someone's personal details.
 */
class CreateBookingAccessCodes extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('booking_access_codes', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            /*
             * Digits only, so the same number matches however it was typed
             * - with spaces, with +61, or with a leading zero.
             */
            ->addColumn('phone_digits', 'string', ['limit' => 20, 'null' => false])
            // Never the code itself: a leaked table would otherwise be a
            // leaked set of working keys.
            ->addColumn('code_hash', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('attempts', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['phone_digits'])
            ->addIndex(['expires_at'])
            ->create();
    }
}
