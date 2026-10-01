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
 * Builds the mailer everything that sends mail goes through.
 *
 * The sending account is set by the developers in .env -
 * EMAIL_TRANSPORT_DEFAULT_URL, EMAIL_FROM and EMAIL_FROM_NAME - and used
 * through the `default` profile in config. It used to be connected from the
 * settings page, which asked the studio for SMTP servers and API tokens;
 * that page now only edits what emails say.
 *
 * An account connected the old way is still used while .env has no
 * transport, so a site set up before the change keeps sending until its
 * .env is filled in.
 *
 * With neither, the config default applies, which on a development machine
 * is XAMPP's mailtodisk - messages land in C:\xampp\mailtodisk rather than
 * being delivered.
 */
class MailerFactory
{
    /** Transport name registered at runtime from the stored settings. */
    protected const RUNTIME_TRANSPORT = 'studio';

    /**
     * A mailer using the account in .env, or one connected the old way.
     *
     * @return \Cake\Mailer\Mailer
     */
    public static function create(): Mailer
    {
        if (env('EMAIL_TRANSPORT_DEFAULT_URL')) {
            return new Mailer('default');
        }

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
