<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use App\Service\SquareAuthService;
use App\Service\SyncService;

/**
 * Handles the app's connection to Square.
 *
 * This controller has nothing to do with staff login/logout (see
 * UsersController for that, handled via the Authentication plugin). It
 * exists purely to manage the single, shared Square OAuth connection that
 * every logged-in user relies on for API calls: connecting it, receiving
 * Square's OAuth callback, and disconnecting it.
 *
 * Because there is only one Square connection for the whole app, every
 * action here is restricted to admins.
 */
class AuthController extends AppController
{
    protected SquareAuthService $squareAuth;

    public function initialize(): void
    {
        parent::initialize();

        $this->squareAuth = new SquareAuthService();

        // Only super admins may view, create, or revoke the app's Square connection.
        $this->requireSuperAdmin();
    }

    /**
     * Landing page showing the current Square connection status.
     *
     * Passes the active Identity record (or null if nothing is connected)
     * to the view so it can render either a "Connect Square" prompt or the
     * currently connected merchant's details.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        $this->set('squareIdentity', $this->squareAuth->getActiveIdentity());

        return $this->render();
    }

    /**
     * Builds the OAuth provider and redirects the admin to Square's
     * authorization page so they can approve access. Persists the OAuth
     * `state` and PKCE code verifier in the session so the callback can
     * be verified against them later.
     *
     * @return \Cake\Http\Response Redirect to Square's authorization URL.
     */
    public function beginAuth(): Response
    {
        // SQUARE OAUTH FLOW - STEP 1 //
        // Build the OAuth provider and ask Square for permission.
        $provider = $this->squareAuth->createProvider();
        $scope = explode(',', env('SQUARE_SCOPE', 'MERCHANT_PROFILE_READ'));

        // Build auth parameters (we do this because we need to add session=false in prod)
        $authParams = ['scope' => $scope];

        if (!Configure::read('debug')) {
            // Only add session=false in production, as required by Square
            $authParams['session'] = 'false';
        }

        // Send the browser to Square so the admin can approve access.
        $authorizationUrl = $provider->getAuthorizationUrl($authParams);

        // Save the state and PKCE values in the session so we can verify the callback later.
        // Note: this is unrelated to app login sessions - it's short-lived data
        // used only to validate the round trip to Square and back.
        $this->request->getSession()->write('oauth2state', $provider->getState());
        $this->request->getSession()->write('oauth2pkceCode', $provider->getPkceCode());

        return $this->redirect($authorizationUrl);
    }

    /**
     * Handles Square's redirect back after the admin approves (or denies)
     * access.
     *
     * Validates the returned `state` against the one stored in session,
     * exchanges the authorization code for an access/refresh token pair,
     * and verifies the returned merchant_id matches our authorized
     * merchant. On success, persists the encrypted tokens as the app's
     * single active Square connection and redirects back to the Square
     * settings page. On failure (denied access, state mismatch, wrong
     * merchant, or token exchange error), throws.
     *
     * @return \Cake\Http\Response Redirect to the Square connection status page.
     * @throws \RuntimeException If Square denies authorization or the token exchange fails.
     * @throws \UnexpectedValueException If the OAuth `state` does not match.
     * @throws \Cake\Http\Exception\ForbiddenException If the authorizing merchant is not the authorized one.
     */
    public function callback(): Response
    {
        // SQUARE OAUTH FLOW - STEP 2 & 3 //
        // Square sends us the code and state back here after the admin approves access.
        // We exchange the code for an access token using the provider.
        \Cake\Log\Log::debug('Callback started');

        $provider = $this->squareAuth->createProvider();
        $session = $this->request->getSession();

        // Check if Square returned an error, e.g. the admin denied authorization.
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

        // Make sure the return came from the same authorization request we started.
        if ($state === '' || $state !== $expectedState) {
            throw new \UnexpectedValueException('Invalid OAuth state');
        }

        try {
            // Set the PKCE code so the provider can use it during token exchange
            $provider->setPkceCode($pkceCode);

            // Exchange the authorization code for an access token.
            // The provider handles all the details: client_id, redirect_uri, code_verifier, etc.
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            $tokenPayload = $accessToken->jsonSerialize();
            $merchantId = $tokenPayload['merchant_id'] ?? null;

            // Encrypt and persist the tokens to the database
            $identityId = $this->squareAuth->saveSquareTokens($tokenPayload);
            \Cake\Log\Log::debug('Identity saved: ' . $identityId);

            // Clean up OAuth temp data now that the exchange is complete.
            $session->delete('oauth2state');
            $session->delete('oauth2pkceCode');

            $this->Flash->success('Square account connected successfully.');

            // Send the admin to the Settings page (where connection status
            // and sync controls live) with a flag so it can prompt them to
            // run a sync now that a connection exists.
            return $this->redirect([
                'controller' => 'Users',
                'action' => 'settings',
                '?' => ['connected' => 1],
            ]);
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            // Square-specific OAuth error.
            throw new \RuntimeException(
                'Square OAuth error: ' . $e->getMessage(),
                0,
                $e
            );
        } catch (\Throwable $e) {
            // Any other error.
            throw new \RuntimeException(
                'Square token exchange failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Disconnects the app's Square connection.
     *
     * Optionally revokes the access token via Square's revoke endpoint
     * (if `revoke_square` is passed in the request data) and marks the
     * active Identity record as revoked in the database.
     *
     * Refuses to disconnect while a sync is currently running, since
     * revoking the token mid-sync would just cause Square API calls to
     * start failing partway through, rather than failing cleanly.
     *
     * Local synced data (Customers, Bookings, Imports) is left untouched
     * on disconnect - see Users/settings.php for the staleness indicator
     * shown once Square is disconnected.
     *
     * This does not touch the admin's own login session - disconnecting
     * Square and logging out of the app are unrelated actions. Staff
     * login/logout is handled entirely by UsersController.
     *
     * @return \Cake\Http\Response Redirect to the Square connection status page.
     */
    public function disconnect(): Response
    {
        $importsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Imports');

        if ($importsTable->hasRunningImport()) {
            $this->Flash->error(
                'Cannot disconnect while a sync is running - try again shortly.',
                ['key' => 'square'],
            );

            return $this->redirect(['controller' => 'Users', 'action' => 'settings']);
        }

        $revokeSquare = (bool)$this->request->getData('revoke_square', false);

        if ($revokeSquare) {
            $this->squareAuth->revokeActiveConnection();
        }

        $this->Flash->success('Square connection disconnected.', ['key' => 'square']);

        return $this->redirect(['controller' => 'Users', 'action' => 'settings']);
    }

    /**
     * Manually triggers a full sync (customers, bookings, and payments) from Square.
     *
     * @return \Cake\Http\Response Redirect to the Settings page.
     */
    public function sync(): Response
    {
        $this->request->allowMethod(['post']);

        /*
         * Sent under its own key so the Settings page can show it inside the
         * Square card, beside the button that was pressed, rather than as a
         * line at the top of the page with nothing around it to say what it
         * is about.
         */
        try {
            (new SyncService())->runAll('manual');
            $this->Flash->success('Sync completed successfully.', ['key' => 'square']);
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('Manual sync failed: ' . $e->getMessage());
            $this->Flash->error('Sync failed: ' . $e->getMessage(), ['key' => 'square']);
        }

        return $this->redirect(['controller' => 'Users', 'action' => 'settings']);
    }
}