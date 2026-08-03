<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use Cake\ORM\TableRegistry;
use Cake\Datasource\EntityInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use Square\SquareClient;
use Square\Environments;
use Square\OAuth\Requests\RevokeTokenRequest;

class SquareAuthService
{
    protected \Cake\ORM\Table $identitiesTable;
    protected \Cake\ORM\Table $sessionsTable;

    public function __construct()
    {
        $this->identitiesTable = TableRegistry::getTableLocator()->get('Identities');
        $this->sessionsTable = TableRegistry::getTableLocator()->get('Sessions');
    }

    public function createProvider(): GenericProvider
    {
        // Determine which Square environment to hit based on CakePHP debug mode
        $environment = Configure::read('debug')
            ? Environments::Sandbox
            : Environments::Production;

        $baseUrl = $environment->value;

        // Configure the provider with the correct Square endpoints and PKCE support.
        return new GenericProvider([
            'clientId' => env('SQUARE_CLIENT_ID', ''),
            'redirectUri' => env('SQUARE_REDIRECT_URI', ''),
            'urlAuthorize' => $baseUrl . '/oauth2/authorize',
            'urlAccessToken' => $baseUrl . '/oauth2/token',
            'urlResourceOwnerDetails' => $baseUrl . '/v2/merchants/me',
            'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
            'scopeSeparator' => ' ',
        ]);
    }

    public function validateSession(?string $sessionId): ?EntityInterface
    {
        if (!$sessionId) {
            return null;
        }

        $sessionRecord = $this->sessionsTable->findValidById($sessionId);
        $authorizedMerchantId = env('SQUARE_AUTHORIZED_MERCHANT_ID');

        if (
            $sessionRecord
            && $sessionRecord->identity
            && !$sessionRecord->identity->revoked
            && $sessionRecord->identity->merchant_id === $authorizedMerchantId
        ) {
            return $sessionRecord;
        }

        return null;
    }

    public function saveSquareTokens(array $tokenPayload): string
    {
        $merchantId = $tokenPayload['merchant_id'] ?? null;

        if (!$merchantId) {
            throw new \Exception('merchant_id is required in token payload');
        }

        $encrypted = $this->identitiesTable->encryptTokens([
            'access_token' => $tokenPayload['access_token'] ?? null,
            'refresh_token' => $tokenPayload['refresh_token'] ?? null,
        ]);

        // Find existing record for this merchant, or create a new one
        $record = $this->identitiesTable->findByMerchantId($merchantId)->first()
            ?? $this->identitiesTable->newEmptyEntity();

        // Patch and save
        $record = $this->identitiesTable->patchEntity($record, [
            'merchant_id' => $merchantId,
            'tokens' => $encrypted,
            'created_at' => $record->isNew() ? FrozenTime::now() : $record->created_at,
            'expires_at' => FrozenTime::parse($tokenPayload['expires_at']),
            'refreshed_at' => null,
            'revoked' => false,
            'revoked_at' => null,
        ]);

        if (!$this->identitiesTable->save($record)) {
            \Cake\Log\Log::error('Failed to save identity: ' . json_encode($record->getErrors()));
            throw new \RuntimeException('Failed to save identity record');
        }

        return (string)$record->id;
    }

    public function createSession(string $identityId): EntityInterface
    {
        $expiresAt = FrozenTime::now()->addHours(24);

        $sessionRecord = $this->sessionsTable->newEntity([
            'identity_id' => $identityId,
            'created_at' => FrozenTime::now(),
            'expires_at' => $expiresAt,
            'revoked' => false,
        ]);

        if (!$this->sessionsTable->save($sessionRecord)) {
            \Cake\Log\Log::error('Failed to save session: ' . json_encode($sessionRecord->getErrors()));
            throw new \RuntimeException('Failed to save session record');
        }

        return $sessionRecord;
    }

    public function revokeSessionById(?string $sessionId): void
    {
        if (!$sessionId) {
            // Nothing to clear
            return;
        }

        $sessionRecord = $this->sessionsTable->find()
            ->where(['id' => $sessionId])
            ->first();

        if ($sessionRecord) {
            $sessionRecord = $this->sessionsTable->patchEntity($sessionRecord, [
                'revoked' => true,
                'revoked_at' => FrozenTime::now(),
            ]);

            if (!$this->sessionsTable->save($sessionRecord)) {
                \Cake\Log\Log::error('Failed to save session: ' . json_encode($sessionRecord->getErrors()));
                throw new \RuntimeException('Failed to save session record');
            }
        }
    }

    public function revokeSquareTokenForSession(?string $sessionId): void
    {
        if (!$sessionId) {
            return;
        }

        $sessionRecord = $this->sessionsTable->find()
            ->where(['Sessions.id' => $sessionId])
            ->contain(['Identities'])
            ->first();

        if (!$sessionRecord || !$sessionRecord->identity) {
            return;
        }

        $identity = $sessionRecord->identity;

        try {
            $accessToken = $this->identitiesTable->getDecryptedAccessToken($identity);

            if ($accessToken) {
                $clientId = env('SQUARE_CLIENT_ID', '');
                $clientSecret = env('SQUARE_CLIENT_SECRET', '');

                // Determine which Square environment based on config
                $environment = Configure::read('debug')
                    ? Environments::Sandbox
                    : Environments::Production;

                // The revoke endpoint requires "Authorization: Client {APPLICATION_SECRET}"
                // instead of the usual "Bearer {token}" format
                $client = new SquareClient(
                    token: $accessToken,
                    options: [
                        'baseUrl' => $environment->value,
                        'headers' => [
                            'Authorization' => 'Client ' . $clientSecret,
                        ],
                    ],
                );

                $client->oAuth->revokeToken(
                    new RevokeTokenRequest([
                        'clientId' => $clientId,
                        'accessToken' => $accessToken,
                    ])
                );
            }
        } catch (\Throwable $e) {
            // Don't block logout if revocation fails just log it
            \Cake\Log\Log::error('Failed to revoke Square token: ' . $e->getMessage());
        }

        // Set identity to revoked
        $identity = $this->identitiesTable->patchEntity($identity, [
            'revoked' => true,
            'revoked_at' => FrozenTime::now(),
        ]);
        $this->identitiesTable->save($identity);
    }
}
