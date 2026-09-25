<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Links a local login account to its record in the Square customer mirror.
 *
 * Nullable because an account starts unlinked: someone can register here
 * before they have ever booked, and only becomes a Square customer once
 * they complete a booking and the next sync pulls that record in.
 *
 * Unique so two accounts cannot claim the same Square customer. MySQL
 * permits any number of NULLs in a unique index, so unlinked accounts are
 * unaffected.
 *
 * Deliberately not a foreign key to customers.id: the sync rebuilds that
 * table's rows, and square_customer_id is the value that stays stable
 * across those rewrites.
 */
class AddSquareCustomerIdToUsers extends BaseMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('square_customer_id', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'after' => 'roles',
            ])
            ->addIndex(['square_customer_id'], ['unique' => true])
            ->update();
    }
}
