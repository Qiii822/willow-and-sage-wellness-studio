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
    /**
     * How old (in days) an access token is allowed to get before it's
     * refreshed on retrieval. Squares docs recommend refreshing
     * every 7 days or less
     */
    protected const REFRESH_THRESHOLD_DAYS = 7;

    protected \Cake\ORM\Table $identitiesTable;

    public function __construct()
    {
        $this->identitiesTable = TableRegistry::getTableLocator()->get('Identities');
    }

    /**
     * Which Square this app talks to.
     *
     * SQUARE_ENVIRONMENT decides ("sandbox" or "production"); left unset it
     * follows debug mode, which is how this used to work. It used to follow
     * debug mode only, so production credentials on a development machine
     * were sent to the sandbox's sign-in page - which answers "Unable to
     * find client by that client_id" and does not say why.
     *
     * Sign-in, token refresh and every API call must agree on this, or a
     * token from one Square is presented to the other.
     *
     * @return \Square\Environments
     */
    public static function environment(): Environments
    {
        $configured = strtolower((string)env('SQUARE_ENVIRONMENT', ''));

        if ($configured === 'production') {
            return Environments::Production;
        }

        if ($configured === 'sandbox') {
            return Environments::Sandbox;
        }

        return Configure::read('debug') ? Environments::Sandbox : Environments::Production;
    }

    /**
     * Builds the League OAuth2 provider configured for Square's endpoints.
     *
     * @return \League\OAuth2\Client\Provider\GenericProvider
     */
    public function createProvider(): GenericProvider
    {
        $baseUrl = self::environment()->value;

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
     * Every time the active identity is retrieved this way, its token age
     * is checked and refreshed if it's due
     *
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function getActiveIdentity(): ?\Cake\Datasource\EntityInterface
    {
        $identity = $this->identitiesTable->getActiveConnection();

        if (!$identity) {
            return null;
        }

        return $this->refreshIfStale($identity);
    }

    /**
     * Refreshes the given identity's access token if it's older than
     * REFRESH_THRESHOLD_DAYS, otherwise returns it unchanged.
     *
     * @param \Cake\Datasource\EntityInterface $identity
     * @return \Cake\Datasource\EntityInterface|null The (possibly refreshed)
     *  identity, or null if Square rejected the refresh outright and the
     *  connection had to be marked revoked.
     */
    protected function refreshIfStale(\Cake\Datasource\EntityInterface $identity): ?\Cake\Datasource\EntityInterface
    {
        $tokenAge = $identity->refreshed_at ?? $identity->created_at;

        if ($tokenAge->wasWithinLast(self::REFRESH_THRESHOLD_DAYS . ' days')) {
            return $identity;
        }

        try {
            return $this->refreshToken($identity);
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            // Square rejected the refresh token outright (expired, already
            // used, or revoked seller-side). The connection can't recover
            // on its own, so mark it revoked locally - this makes the app
            // accurately show "disconnected" and prompt an admin to
            // reconnect, rather than silently retrying forever with a
            // refresh token Square will never accept again.
            \Cake\Log\Log::error('Square refresh token rejected, marking connection revoked: ' . $e->getMessage());

            $identity = $this->identitiesTable->patchEntity($identity, [
                'revoked' => true,
                'revoked_at' => DateTime::now(),
            ]);
            $this->identitiesTable->save($identity);

            return null;
        } catch (\Throwable $e) {
            // Transient failure (network blip, Square-side outage). The
            // existing access token is still valid for now, log it and
            // try again next time the identity is retrieved, rather than
            // breaking whatever action triggered this lookup.
            \Cake\Log\Log::error('Square token refresh attempt failed, will retry later: ' . $e->getMessage());

            return $identity;
        }
    }

    /**
     * Exchanges the identity's stored refresh token for a new
     * access/refresh token pair and persists them on the same Identity
     * row.
     *
     * @param \Cake\Datasource\EntityInterface $identity
     * @return \Cake\Datasource\EntityInterface The identity with rotated tokens.
     * @throws \RuntimeException If there's no refresh token to use, or
     *  Square's response is missing required token fields.
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     *  If Square rejects the refresh token.
     */
    public function refreshToken(\Cake\Datasource\EntityInterface $identity): \Cake\Datasource\EntityInterface
    {
        $refreshToken = $this->identitiesTable->getDecryptedRefreshToken($identity);

        if (!$refreshToken) {
            throw new \RuntimeException('No refresh token available on the active Square connection.');
        }

        $provider = $this->createProvider();

        // SQUARE OAUTH FLOW - TOKEN REFRESH //
        // Refresh tokens obtained via the PKCE flow are single-use: Square
        // returns a brand new refresh_token in this response, which we
        // must store in place of the one we just used.
        $accessToken = $provider->getAccessToken('refresh_token', [
            'refresh_token' => $refreshToken,
        ]);

        $tokenPayload = $accessToken->jsonSerialize();

        if (
            empty($tokenPayload['access_token'])
            || empty($tokenPayload['refresh_token'])
            || empty($tokenPayload['expires_at'])
        ) {
            throw new \RuntimeException('Square refresh response was missing required token fields.');
        }

        return $this->identitiesTable->applyRefreshedTokens(
            $identity,
            $tokenPayload['access_token'],
            $tokenPayload['refresh_token'],
            DateTime::parse($tokenPayload['expires_at']),
        );
    }

    /**
     * Revokes the app's active Square connection: calls Square's revoke
     * endpoint for the current access token, then marks the Identity
     * record as revoked locally regardless of whether the remote call
     * succeeded (so a failed revoke call never leaves the app thinking
     * it still has a usable connection).
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

                $environment = self::environment();

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
                        /*
                         * Only this copy's token. Left to its default, Square
                         * revokes every token the app holds for the merchant -
                         * so disconnecting a development machine from the
                         * studio's account would cut the live site off too.
                         */
                        'revokeOnlyAccessToken' => true,
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