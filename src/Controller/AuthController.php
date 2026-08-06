<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Cookie\Cookie;
use App\Service\SquareAuthService;

class AuthController extends AppController
{
    protected SquareAuthService $squareAuth;

    public function initialize(): void
    {
        parent::initialize();

        $this->squareAuth = new SquareAuthService();
    }

    public function index(): Response
    {
        // If already authenticated, skip straight to dashboard
        if ($this->isAuthenticated()) {
            return $this->redirect(['controller' => 'Dashboard', 'action' => 'index']);
        }

        // This is the landing page for the auth flow.
        return $this->render();
    }

    /**
     * Builds the OAuth provider and redirects the user to Square's
     * authorization page so they can approve access. Persists the OAuth
     * `state` and PKCE code verifier in the session so the callback can
     * be verified against them later.
     *
     * @return \Cake\Http\Response Redirect to Square's authorization URL.
     */
    public function beginAuth(): Response
    {
        //SQUARE OAUTH Flow STEP 1 //
        // Build the OAuth provider and ask Square for permission.
        $provider = $this->squareAuth->createProvider();
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

    /**
     * Handles Square's redirect back after the user interacts with square.
     * Validates the returned `state` against the one stored in
     * session, exchanges the authorization code for an access/refresh
     * token pair, and verifies the returned merchant_id matches our
     * authorized merchant.
     * 
     * On success, persists the encrypted tokens,
     * creates a session record + cookie, and redirects the user back to
     * wherever they originally tried to go. On failure (denied access,
     * state mismatch, wrong merchant, or token exchange error), throws.
     *
     * @return \Cake\Http\Response Redirect to the original requested page, or `/`.
     * @throws \RuntimeException If Square denies authorization or the token exchange fails.
     * @throws \UnexpectedValueException If the OAuth `state` does not match.
     * @throws \Cake\Http\Exception\ForbiddenException If the authenticated merchant is not the authorized one.
     */
    public function callback(): Response
    {
        //SQUARE OAUTH Flow STEP 2 & 3 //
        // Square sends us the code and state back here after the user approves access.
        // We exchange the code for an access token using the provider.
        \Cake\Log\Log::debug('Callback started');

        $provider = $this->squareAuth->createProvider();
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
            $identityId = $this->squareAuth->saveSquareTokens($tokenPayload);
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

    /**
     * Logs the current user out.
     * Optionally revokes the Square access token via Square's revoke
     * endpoint (if `revoke_square` is passed in the request data), marks
     * the associated session record as revoked in the database, clears
     * all OAuth/session data from the PHP session, destroys the session,
     * and expires the `session_id` cookie.
     *
     * @return \Cake\Http\Response Redirect to the auth index page with an expired session cookie.
     */
    public function logout(): Response
    {
        $revokeSquare = (bool)$this->request->getData('revoke_square', false);

        $session = $this->request->getSession();
        $sessionId = $this->request->getCookie('session_id');

        if ($revokeSquare) {
            $this->squareAuth->revokeSquareTokenForSession($sessionId);
        }

        // Remove the persisted session from the database
        $this->squareAuth->revokeSessionById($sessionId);

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

    /**
     * Persists a new session record for the given identity and writes
     * the corresponding session_id cookie + PHP session values.
     *
     * @param string $identityId The identity this session belongs to.
     * @param string $merchantId The merchant_id associated with this identity.
     * @return void
     */
    private function saveSession(string $identityId, string $merchantId): void
    {
        $session = $this->request->getSession();
        $sessionRecord = $this->squareAuth->createSession($identityId);

        // Store session identifier in PHP session
        $session->write('identity_id', $identityId);
        $session->write('merchant_id', $merchantId);
        $session->write('session_id', $sessionRecord->id);

        // Set secure cookie with session ID
        $this->response = $this->response
            ->withCookie(new Cookie(
                'session_id',
                (string)$sessionRecord->id,
                $sessionRecord->expires_at,
                '/',
                '',
                true,
                true,
                'Strict'
            ));
    }

    /**
     * Checks whether the current request has a valid, unrevoked session
     * tied to the authorized merchant.
     */
    private function isAuthenticated(): bool
    {
        $sessionId = $this->request->getCookie('session_id');

        return $this->squareAuth->validateSession($sessionId) !== null;
    }
}
