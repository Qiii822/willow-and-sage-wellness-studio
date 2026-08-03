<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Utility\Security;
use Cake\I18n\FrozenTime;
use Cake\Http\Cookie\Cookie;
use League\OAuth2\Client\Provider\GenericProvider;
use Square\SquareClient;
use Square\Environments;
use Square\OAuth\Requests\RevokeTokenRequest;

class AuthController extends AppController
{
    public function index(): Response
    {
        // If already authenticated, skip straight to dashboard
        if ($this->isAuthenticated()) {
            return $this->redirect(['controller' => 'Dashboard', 'action' => 'index']);
        }

        // This is the landing page for the auth flow.
        return $this->render();
    }

    public function login(): Response
    {
        //SQUARE OAUTH Flow STEP 1 //
        // Build the OAuth provider and ask Square for permission.
        $provider = $this->createProvider();
        $scope = explode(',', env('SQUARE_SCOPE', 'MERCHANT_PROFILE_READ'));

        //Build auth parameters (we do this because we need to add session=false in prod)
        $authParams = ['scope' => $scope];

        if ( !Configure::read('debug') ) {
            // Only add session=false in production as required by Square
            $authParams['session'] = 'false';
        }

        // Send the browser to Square so the user can approve access.
        $authorizationUrl = $provider->getAuthorizationUrl($authParams);

        // Save the state and PKCE values in the session so we can verify the callback later.
        $this->request->getSession()->write('oauth2state', $provider->getState());
        $this->request->getSession()->write('oauth2pkceCode', $provider->getPkceCode());

        return $this->redirect($authorizationUrl);
    }

    public function callback(): Response
    {
        //SQUARE OAUTH Flow STEP 2 & 3 //
        // Square sends us the code and state back here after the user approves access.
        // We exchange the code for an access token using the provider.
        \Cake\Log\Log::debug('Callback started');

        $provider = $this->createProvider();
        $session = $this->request->getSession();

        // Check if Square returns an error
        // For example the user denied auth
        $error = $this->request->getQuery('error');
        $errorDescription = $this->request->getQuery('error_description');
        
        if ($error) {
            $session->delete('oauth2state');
            $session->delete('oauth2pkceCode');
            throw new \RuntimeException(
                'Square authorization denied: ' . $error . 
                ($errorDescription ? ' - ' . $errorDescription : '')
            );
        }

        $code = (string)$this->request->getQuery('code');
        $state = (string)$this->request->getQuery('state');
        $expectedState = (string)$session->read('oauth2state');
        $pkceCode = (string)$session->read('oauth2pkceCode');

        // Make sure the return came from the same login request we started.
        if ($state === '' || $state !== $expectedState) {
            throw new \UnexpectedValueException('Invalid OAuth state');
        }

        try {
            // Set the PKCE code so the provider can use it during token exchange
            $provider->setPkceCode($pkceCode);

            // Exchange the authorization code for an access token
            // The provider handles all the details: client_id, redirect_uri, code_verifier, etc.
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            $tokenPayload = $accessToken->jsonSerialize();
            $merchantId = $tokenPayload['merchant_id'] ?? null;

            // Verify the merchant is authorized
            // We do this because we only want access to our client.
            if ($merchantId !== env('SQUARE_AUTHORIZED_MERCHANT_ID')) {
                // Wrong merchant, reject
                $session->delete('oauth2state');
                $session->delete('oauth2pkceCode');
                throw new ForbiddenException(
                    'This Square account is not authorized to access this application.'
                );
            }

            // Correct merchant, persist the tokens (encrypted) to the database
            $identityId = $this->saveSquareTokens($tokenPayload);
            \Cake\Log\Log::debug('Identity saved: ' . $identityId);

            // Create session and cookie
            $this->saveSession($identityId, $merchantId);
            \Cake\Log\Log::debug('Session saved');

            // Clean up OAuth temp data
            $session->delete('oauth2state');
            $session->delete('oauth2pkceCode');

            $redirectUrl = $session->read('redirect_after_auth');
            $session->delete('redirect_after_auth');

            if (!$redirectUrl || !str_starts_with($redirectUrl, '/')) {
                $redirectUrl = '/';
            }

            return $this->redirect($redirectUrl);
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            // Square-specific OAuth error
            throw new \RuntimeException(
                'Square OAuth error: ' . $e->getMessage(),
                0,
                $e
            );
        } catch (\Throwable $e) {
            // Any other error
            throw new \RuntimeException(
                'Square token exchange failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function logout(): Response
    {
        $revokeSquare = (bool)$this->request->getData('revoke_square', false);

        $session = $this->request->getSession();

        if ($revokeSquare) {
            $this->revokeSquareTokens();
        }

        // Remove the persisted session from the database
        $this->revokeSession();

        // Clear all Square OAuth data from session
        $session->delete('identity_id');
        $session->delete('merchant_id');
        $session->delete('session_id');
        $session->delete('oauth2state');
        $session->delete('oauth2pkceCode');
        $session->delete('redirect_after_auth');

        $session->destroy();

        $response = $this->redirect(['controller' => 'Auth', 'action' => 'index']);

        return $response->withExpiredCookie(new Cookie('session_id'));
    }

    private function createProvider(): GenericProvider
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

    private function saveSquareTokens(array $tokenPayload): string
    {
        $identitiesTable = $this->fetchTable('Identities');
        $merchantId = $tokenPayload['merchant_id'] ?? null;

        if (!$merchantId) {
            throw new \Exception('merchant_id is required in token payload');
        }

        $encrypted = $identitiesTable->encryptTokens([
            'access_token' => $tokenPayload['access_token'] ?? null,
            'refresh_token' => $tokenPayload['refresh_token'] ?? null,
        ]);

        // Find existing record for this merchant, or create a new one
        $record = $identitiesTable->findByMerchantId($merchantId)->first()
            ?? $identitiesTable->newEmptyEntity();

        // Patch and save
        $record = $identitiesTable->patchEntity($record, [
            'merchant_id' => $merchantId,
            'tokens' => base64_encode($encrypted),
            'created_at' => $record->isNew() ? FrozenTime::now() : $record->created_at,
            'expires_at' => FrozenTime::parse($tokenPayload['expires_at']),
            'refreshed_at' => null,
            'revoked' => false,
            'revoked_at' => null,
        ]);

        if (!$identitiesTable->save($record)) {
            \Cake\Log\Log::error('Failed to save identity: ' . json_encode($record->getErrors()));
            throw new \RuntimeException('Failed to save identity record');
        }
        

        return (string)$record->id;
    }

    private function revokeSquareTokens(): void
    {
        $sessionId = $this->request->getCookie('session_id');

        if (!$sessionId) {
            return;
        }

        $sessionsTable = $this->fetchTable('Sessions');
        $sessionRecord = $sessionsTable->find()
            ->where(['Sessions.id' => $sessionId])
            ->contain(['Identities'])
            ->first();

        if (!$sessionRecord || !$sessionRecord->identity) {
            return;
        }

        $identity = $sessionRecord->identity;
        $identitiesTable = $this->fetchTable('Identities');

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
        $identity = $identitiesTable->patchEntity($identity, [
            'revoked' => true,
            'revoked_at' => FrozenTime::now(),
        ]);
        $identitiesTable->save($identity);
    }

    private function saveSession(string $identityId, string $merchantId): void
    {
        $session = $this->request->getSession();
        $sessionsTable = $this->fetchTable('Sessions');
        $expiresAt = FrozenTime::now()->addHours(24);

        // Create a session record in the database
        $sessionRecord = $sessionsTable->newEntity([
            'identity_id' => $identityId,
            'created_at' => FrozenTime::now(),
            'expires_at' => $expiresAt,
            'revoked' => false,
        ]);

        if (!$sessionsTable->save($sessionRecord)) {
            \Cake\Log\Log::error('Failed to save session: ' . json_encode($sessionRecord->getErrors()));
            throw new \RuntimeException('Failed to save session record');
        }

        // Store session identifier in PHP session
        $session->write('identity_id', $identityId);
        $session->write('merchant_id', $merchantId);
        $session->write('session_id', $sessionRecord->id);

        // Set secure cookie with session ID
        $this->response = $this->response
            ->withCookie(new Cookie(
                'session_id',
                (string)$sessionRecord->id,
                $expiresAt,
                '/',
                '',
                true,
                true,
                'Strict'
            ));
    }

    private function revokeSession(): void
    {
        $sessionId = $this->request->getCookie('session_id');

        if (!$sessionId) {
            // Nothing to clear
            return;
        }

        $sessionsTable = $this->fetchTable('Sessions');
        $sessionRecord = $sessionsTable->find()
            ->where(['id' => $sessionId])
            ->first();

        if ($sessionRecord) {
            $sessionRecord = $sessionsTable->patchEntity($sessionRecord, [
                'revoked' => true,
                'revoked_at' => FrozenTime::now(),
            ]);

            if (!$sessionsTable->save($sessionRecord)) {
                \Cake\Log\Log::error('Failed to save session: ' . json_encode($sessionRecord->getErrors()));
                throw new \RuntimeException('Failed to save session record');
            }
        }
    }
    private function isAuthenticated(): bool
    {
        $sessionId = $this->request->getCookie('session_id');

        if (!$sessionId) {
            return false;
        }

        $sessionsTable = $this->fetchTable('Sessions');
        $sessionRecord = $sessionsTable->find()
            ->where([
                'Sessions.id' => $sessionId,
                'Sessions.revoked' => false,
            ])
            ->andWhere(['Sessions.expires_at >' => \Cake\I18n\FrozenTime::now()])
            ->contain(['Identities'])
            ->first();

        return $sessionRecord
            && $sessionRecord->identity
            && !$sessionRecord->identity->revoked
            && $sessionRecord->identity->merchant_id === env('SQUARE_AUTHORIZED_MERCHANT_ID');
    }
}