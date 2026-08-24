<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Short-lived codes proving someone can receive mail at an address, or SMS
 * on a number, before that value replaces the one on their account.
 *
 * The new value lives here rather than on the user until it is confirmed,
 * so an unverified address never becomes the account's contact detail - and
 * never becomes the address a password reset would be sent to.
 */
class CreateVerificationCodes extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('verification_codes', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('user_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('channel', 'string', [
                'limit' => 10,
                'null' => false,
                'comment' => 'email or sms',
            ])
            // The pending value, held here until the code is accepted.
            ->addColumn('destination', 'string', ['limit' => 255, 'null' => false])
            // Hashed: a readable code in the database would be a credential
            // anyone with table access could use.
            ->addColumn('code_hash', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('attempts', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('consumed_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            // One lookup pattern: the live code for this user and channel.
            ->addIndex(['user_id', 'channel'])
            ->addIndex(['expires_at'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();
    }
}
