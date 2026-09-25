<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Security;
use Cake\Validation\Validator;

class MailSettingsTable extends Table
{
    /** Credentials are an SMTP username and password. */
    public const MODE_SMTP = 'smtp';

    /** A single HTTP API token, sent over 443. */
    public const MODE_API = 'api';

    /**
     * Providers offered on the settings page, so the studio only has to
     * supply credentials rather than look up host and port.
     *
     * Each provider lists the ways it can be connected. Most speak only
     * SMTP and so have a single mode, which the form hides; Mailtrap offers
     * both an API token and SMTP, and there the choice is shown.
     *
     * @var array<string, array<string, mixed>>
     */
    public const PROVIDERS = [
        'mailtrap' => [
            'label' => 'Mailtrap',
            'blurb' => 'API token or SMTP',
            'modes' => [
                self::MODE_API => [
                    'label' => 'API token',
                    'hint' => 'Paste the token from Mailtrap. Nothing else is needed.',
                    // The endpoint is fixed by the driver; 443 is recorded
                    // only so the stored row reflects how it connects.
                    'host' => null,
                    'port' => 443,
                    'tls' => true,
                ],
                self::MODE_SMTP => [
                    'label' => 'SMTP',
                    'hint' => 'Username is the word "api"; the password is that same token.',
                    'host' => 'live.smtp.mailtrap.io',
                    'port' => 587,
                    'tls' => true,
                ],
            ],
        ],
        'gmail' => [
            'label' => 'Gmail',
            'blurb' => 'Send via a Google account',
            'modes' => [
                self::MODE_SMTP => [
                    'label' => 'SMTP',
                    'hint' => 'Needs a Google app password, not your usual one.',
                    'host' => 'smtp.gmail.com',
                    'port' => 587,
                    'tls' => true,
                ],
            ],
        ],
        'outlook' => [
            'label' => 'Outlook',
            'blurb' => 'Microsoft / Office 365',
            'modes' => [
                self::MODE_SMTP => [
                    'label' => 'SMTP',
                    'hint' => null,
                    'host' => 'smtp.office365.com',
                    'port' => 587,
                    'tls' => true,
                ],
            ],
        ],
        'other' => [
            'label' => 'Other',
            'blurb' => 'Custom SMTP server',
            'modes' => [
                self::MODE_SMTP => [
                    'label' => 'SMTP',
                    'hint' => 'Enter the server details your provider gave you.',
                    'host' => null,
                    'port' => 587,
                    'tls' => true,
                ],
            ],
        ],
    ];

    /**
     * The settings for one provider and connection mode.
     *
     * @param string $provider
     * @param string|null $mode Falls back to the provider's first mode.
     * @return array<string, mixed>|null Null when either is unrecognised.
     */
    public static function preset(string $provider, ?string $mode = null): ?array
    {
        $modes = self::PROVIDERS[$provider]['modes'] ?? null;

        if (!$modes) {
            return null;
        }

        $mode ??= array_key_first($modes);

        if (!isset($modes[$mode])) {
            return null;
        }

        return $modes[$mode] + ['auth_mode' => $mode];
    }

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('mail_settings');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'modified' => 'always',
                ],
            ],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('provider')
            ->inList('provider', array_keys(self::PROVIDERS), 'Choose one of the listed providers.');

        /*
         * Host, port and username only apply to SMTP. In API mode the
         * endpoint is fixed by the driver and the token is the whole
         * credential, so requiring them would block a valid setup.
         */
        $isSmtp = fn (array $context): bool
            => ($context['data']['auth_mode'] ?? self::MODE_SMTP) === self::MODE_SMTP;

        $validator
            ->scalar('host')
            ->notEmptyString('host', 'An SMTP server address is required.', $isSmtp);

        $validator
            ->integer('port')
            ->range('port', [1, 65535], 'Enter a valid port number.');

        $validator
            ->scalar('username')
            ->notEmptyString('username', 'A username is required.', $isSmtp);

        $validator
            ->email('from_email', false, 'Enter a valid sending address.')
            ->notEmptyString('from_email', 'A sending address is required.');

        return $validator;
    }

    /**
     * The connected mail account, or null when none has been set up.
     *
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function getActive()
    {
        return $this->find()->orderBy(['modified' => 'DESC'])->first();
    }

    /**
     * The key used to encrypt the stored password.
     *
     * Follows the same source as the Square tokens, falling back to the
     * application salt. Returns null when neither holds a real value -
     * notably when the salt is still the `__SALT__` placeholder that ships
     * in the repository, which would be no secret at all.
     *
     * @return string|null
     */
    public static function encryptionKey(): ?string
    {
        /*
         * Security::getSalt(), not Configure::read('Security.salt') - the
         * framework's bootstrap consumes that config key, so reading it back
         * always returns null once the application has started.
         */
        foreach ([
            (string)env('TOKEN_ENCRYPTION_KEY'),
            (string)Security::getSalt(),
        ] as $candidate) {
            // Security::encrypt refuses anything under 32 bytes, and the
            // shipped placeholder must never be treated as a secret.
            if (strlen($candidate) >= 32 && $candidate !== '__SALT__') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param string $plain
     * @return string Base64 of the encrypted value.
     * @throws \RuntimeException When no usable key is configured.
     */
    public function encryptPassword(string $plain): string
    {
        $key = self::encryptionKey();

        if ($key === null) {
            throw new \RuntimeException('No encryption key is configured.');
        }

        return base64_encode(Security::encrypt($plain, $key));
    }

    /**
     * @param \Cake\Datasource\EntityInterface $settings
     * @return string|null Null when the value cannot be decrypted, e.g. the
     *   key changed after the password was stored.
     */
    public function decryptPassword($settings): ?string
    {
        $key = self::encryptionKey();

        if ($key === null || empty($settings->password_encrypted)) {
            return null;
        }

        $decrypted = Security::decrypt(
            base64_decode($settings->password_encrypted),
            $key
        );

        return $decrypted === false ? null : $decrypted;
    }
}
