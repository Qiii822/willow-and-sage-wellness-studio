<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * The wording of emails the studio sends to customers.
 *
 * Kept apart from site_contents on purpose: the home page editor's "Restore
 * original wording" clears that whole table, and resetting the home page
 * should not quietly reset an email too.
 */
class CreateEmailContents extends BaseMigration
{
    public function up(): void
    {
        $this->table('email_contents', [
            'id' => false,
            'primary_key' => ['id'],
        ])
            ->addColumn('id', 'uuid')
            ->addColumn('content_key', 'string', [
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('value', 'text', [
                'null' => true,
            ])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['content_key'], ['unique' => true])
            ->create();
    }

    public function down(): void
    {
        $this->table('email_contents')->drop()->save();
    }
}
