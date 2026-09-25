<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Stores the account holder's mobile number.
 *
 * Square anchors a buyer's account on their mobile, so a returning customer
 * enters the same number at checkout in order to reach their bookings. That
 * makes the phone a stronger key than the email for tying a booking back to
 * a local account.
 *
 * Held in E.164 (`+61415555365`) so the unique index compares numbers rather
 * than the punctuation people happen to type. Nullable because the eleven
 * accounts that predate this column have no number on file; MySQL allows any
 * number of NULLs in a unique index.
 */
class AddPhoneToUsers extends BaseMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('phone', 'string', [
                'limit' => 20,
                'null' => true,
                'default' => null,
                'after' => 'surname',
            ])
            ->addIndex(['phone'], ['unique' => true])
            ->update();
    }
}
