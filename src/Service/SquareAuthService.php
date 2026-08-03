<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
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

    /**
     * Builds the League OAuth2 provider configured for Square's endpoints.
     *
     * @return \League\OAuth2\Client\Provider\GenericProvider
     */
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

    /**
     * Validates a session_id cookie value against the database.
     * Makes sure the session is good, the identity behind it hasn't been
     * revoked, and it's actually our authorized merchant.
     *
     * @param string|null $sessionId The session_id cookie value.
     * @return \Cake\Datasource\EntityInterface|null The session record (with ->identity loaded), or null.
     */
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

    /**
     * Encrypts and persists the access/refresh tokens returned by Square,
     * creating or updating the Identity record for this merchant.
     *
     * @param array $tokenPayload The token payload returned from the OAuth exchange.
     * @return string The saved identity's id.
     */
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
            'created_at' => $record->isNew() ? DateTime::now() : $record->created_at,
            'expires_at' => DateTime::parse($tokenPayload['expires_at']),
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

    /**
     * Creates a new session record in the database for the given identity.
     *
     * @param string $identityId The identity to attach this session to.
     * @return \Cake\Datasource\EntityInterface The saved session record.
     */
    public function createSession(string $identityId): EntityInterface
    {
        $expiresAt = DateTime::now()->addHours(24);

        $sessionRecord = $this->sessionsTable->newEntity([
            'identity_id' => $identityId,
            'created_at' => DateTime::now(),
            'expires_at' => $expiresAt,
            'revoked' => false,
        ]);

        if (!$this->sessionsTable->save($sessionRecord)) {
            \Cake\Log\Log::error('Failed to save session: ' . json_encode($sessionRecord->getErrors()));
            throw new \RuntimeException('Failed to save session record');
        }

        return $sessionRecord;
    }

    /**
     * Marks a session record as revoked. Safe to call with a null/missing id.
     *
     * @param string|null $sessionId The session_id cookie value.
     * @return void
     */
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
                'revoked_at' => DateTime::now(),
            ]);

            if (!$this->sessionsTable->save($sessionRecord)) {
                \Cake\Log\Log::error('Failed to save session: ' . json_encode($sessionRecord->getErrors()));
                throw new \RuntimeException('Failed to save session record');
            }
        }
    }

    /**
     * Revokes the Square access token tied to a session's identity (calling
     * Square's revoke endpoint) and marks that identity as revoked locally.
     *
     * @param string|null $sessionId The session_id cookie value.
     * @return void
     */
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
            'revoked_at' => DateTime::now(),
        ]);
        $this->identitiesTable->save($identity);
    }
}
