<?php
declare(strict_types=1);

namespace App\Service;

use App\Mailer\Transport\MailtrapApiTransport;
use App\Model\Table\MailSettingsTable;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;
use Cake\ORM\TableRegistry;

/**
 * Builds mailers that use whatever account the studio connected on the
 * settings page.
 *
 * Everything that sends mail goes through here rather than calling
 * `new Mailer('default')`, so connecting an account in the admin UI takes
 * effect everywhere without editing config files or restarting anything.
 *
 * With nothing connected it falls back to the transport in config, which on
 * a development machine is XAMPP's mailtodisk - messages land in
 * C:\xampp\mailtodisk rather than being delivered.
 */
class MailerFactory
{
    /** Transport name registered at runtime from the stored settings. */
    protected const RUNTIME_TRANSPORT = 'studio';

    /**
     * A mailer configured from the connected account, or the config default.
     *
     * @return \Cake\Mailer\Mailer
     */
    public static function create(): Mailer
    {
        $settings = static::activeSettings();

        if ($settings === null) {
            return new Mailer('default');
        }

        $table = TableRegistry::getTableLocator()->get('MailSettings');
        $password = $table->decryptPassword($settings);

        if ($password === null) {
            /*
             * Stored under a key we no longer hold, so the credentials are
             * unusable. Falling back keeps mail flowing to wherever the
             * config points instead of throwing mid-request.
             */
            Log::error('Stored mail password could not be decrypted; using the configured transport.');

            return new Mailer('default');
        }

        TransportFactory::drop(static::RUNTIME_TRANSPORT);
        TransportFactory::setConfig(
            static::RUNTIME_TRANSPORT,
            static::transportConfig($settings, $password)
        );

        $mailer = new Mailer();
        $mailer->setTransport(static::RUNTIME_TRANSPORT);
        $mailer->setFrom(
            $settings->from_name
                ? [$settings->from_email => $settings->from_name]
                : $settings->from_email
        );

        return $mailer;
    }

    /**
     * The transport definition for a set of stored settings.
     *
     * Shared with the settings page's connection test, so what gets proved
     * before saving is exactly what will be used afterwards.
     *
     * @param \Cake\Datasource\EntityInterface $settings
     * @param string $secret The decrypted password or API token.
     * @return array<string, mixed>
     */
    public static function transportConfig($settings, string $secret): array
    {
        if (($settings->auth_mode ?? MailSettingsTable::MODE_SMTP) === MailSettingsTable::MODE_API) {
            return [
                'className' => MailtrapApiTransport::class,
                'token' => $secret,
                'timeout' => 30,
            ];
        }

        return [
            'className' => 'Smtp',
            'host' => $settings->host,
            'port' => (int)$settings->port,
            'username' => $settings->username,
            'password' => $secret,
            'tls' => (bool)$settings->tls,
            'timeout' => 30,
        ];
    }

    /**
     * True when an account has been connected on the settings page.
     *
     * @return bool
     */
    public static function isConfigured(): bool
    {
        return static::activeSettings() !== null;
    }

    /**
     * @return \Cake\Datasource\EntityInterface|null
     */
    protected static function activeSettings()
    {
        try {
            return TableRegistry::getTableLocator()->get('MailSettings')->getActive();
        } catch (\Throwable $e) {
            // The table may not exist yet on a database that has not been
            // migrated; mail should still send through the config default.
            return null;
        }
    }
}
