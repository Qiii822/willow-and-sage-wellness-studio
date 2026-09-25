<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Whether the stored credential is an SMTP password or an HTTP API token.
 *
 * Providers hand out one or the other, and expecting the studio owner to
 * know that a Mailtrap API token doubles as an SMTP password under the
 * username "api" is not reasonable. Recording the mode lets the settings
 * page ask for exactly what the provider gave them.
 */
class AddAuthModeToMailSettings extends BaseMigration
{
    public function change(): void
    {
        $this->table('mail_settings')
            ->addColumn('auth_mode', 'string', [
                'limit' => 10,
                'null' => false,
                'default' => 'smtp',
                'comment' => 'smtp or api',
                'after' => 'provider',
            ])
            // Unused in api mode, where the endpoint is fixed by the driver.
            ->changeColumn('host', 'string', ['limit' => 255, 'null' => true])
            ->changeColumn('username', 'string', ['limit' => 255, 'null' => true])
            ->update();
    }
}
