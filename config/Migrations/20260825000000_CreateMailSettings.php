<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * The outgoing mail account the studio connects from the admin settings
 * page, so mail delivery can be set up without editing config files.
 *
 * One row, holding whichever provider is currently connected. The password
 * is encrypted before it is written; see MailSettingsTable.
 */
class CreateMailSettings extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('mail_settings', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('provider', 'string', [
                'limit' => 20,
                'null' => false,
                'comment' => 'mailtrap, gmail, outlook or other',
            ])
            ->addColumn('host', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('port', 'integer', ['null' => false, 'default' => 587])
            ->addColumn('username', 'string', ['limit' => 255, 'null' => false])
            // Encrypted at rest, never stored or displayed in the clear.
            ->addColumn('password_encrypted', 'text', ['null' => false])
            ->addColumn('tls', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('from_email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('from_name', 'string', ['limit' => 255, 'null' => true])
            // Cleared whenever the settings change, set by a successful test.
            ->addColumn('verified_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->create();
    }
}
