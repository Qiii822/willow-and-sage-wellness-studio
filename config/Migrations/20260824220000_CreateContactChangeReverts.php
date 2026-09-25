<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Lets the previous owner of an address undo a contact change they did not
 * make.
 *
 * The notification sent to the old address carries a link backed by one of
 * these rows. It is deliberately long-lived: the point is to still work when
 * someone reads the warning email days later.
 *
 * The token is stored as a SHA-256 digest rather than in the clear, so a
 * leaked database dump cannot be used to take accounts back over. Lookup
 * hashes the incoming token and matches on that, which an index still
 * serves - unlike a slow password hash.
 */
class CreateContactChangeReverts extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('contact_change_reverts', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('user_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('field', 'string', [
                'limit' => 10,
                'null' => false,
                'comment' => 'email or phone',
            ])
            // What to put back if the change is rejected.
            ->addColumn('previous_value', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('new_value', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('token_hash', 'char', ['limit' => 64, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['token_hash'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();
    }
}
