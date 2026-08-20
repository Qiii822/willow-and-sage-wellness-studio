<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use League\OAuth2\Client\Provider\GenericProvider;
use Square\SquareClient;
use Square\Environments;
use Square\OAuth\Requests\RevokeTokenRequest;

/**
 * Manages the app's single, shared connection to Square.
 *
 * This service has nothing to do with staff login/logout - it owns only
 * the OAuth mechanics (building the provider, exchanging codes for tokens)
 * and the one active Identity record that every user's API calls rely on.
 * There is exactly one active (non-revoked) Identity at a time; this class
 * does not key anything off a particular user or session.
 */
class SquareAuthService
{
    protected \Cake\ORM\Table $identitiesTable;

    public function __construct()
    {
        $this->identitiesTable = TableRegistry::getTableLocator()->get('Identities');
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
     * Encrypts and persists the access/refresh tokens returned by Square,
     * creating or updating the single Identity record for the authorized
     * merchant.
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

        // Find any active merchants and revoke them.
        // This is unlikely to happen, but just in-case to ensure a single
        // active connection.
        $active = $this->identitiesTable->getActiveConnection();
        if ($active) {
            $active->revoked = true;
            $active->revoked_at = DateTime::now();
            if (!$this->identitiesTable->save($active)) {
                \Cake\Log\Log::error('Failed to revoke previous identity: ' . json_encode($active->getErrors()));
                throw new \RuntimeException('Failed to revoke previous identity record');
            }
        }

        // Add this new row to the identities table
        $record = $this->identitiesTable->newEmptyEntity();
        $record = $this->identitiesTable->patchEntity($record, [
            'merchant_id' => $merchantId,
            'tokens' => $encrypted,
            'created_at' => DateTime::now(),
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
     * Returns the app's currently active (non-revoked) Square Identity, or
     * null if Square has never been connected or the connection has been
     * revoked.
     *
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function getActiveIdentity(): ?\Cake\Datasource\EntityInterface
    {
        return $this->identitiesTable->getActiveConnection();
    }

    /**
     * Revokes the app's active Square connection: calls Square's revoke
     * endpoint for the current access token, then marks the Identity
     * record as revoked locally regardless of whether the remote call
     * succeeded (so a failed revoke call never leaves the app thinking
     * it still has a usable connection).
     *
     * No-ops if there is no active connection to revoke.
     *
     * @return void
     */
    public function revokeActiveConnection(): void
    {
        $identity = $this->getActiveIdentity();

        if (!$identity) {
            return;
        }

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
            // Don't block disconnecting locally if the remote revoke call
            // fails - just log it. Square-side revocation is best-effort;
            // marking our own record revoked below is what actually stops
            // the app from using this connection.
            \Cake\Log\Log::error('Failed to revoke Square token: ' . $e->getMessage());
        }

        // Mark the identity as revoked locally regardless of whether the
        // Square API call above succeeded.
        $identity = $this->identitiesTable->patchEntity($identity, [
            'revoked' => true,
            'revoked_at' => DateTime::now(),
        ]);
        $this->identitiesTable->save($identity);
    }
}