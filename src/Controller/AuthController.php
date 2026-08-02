<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use League\OAuth2\Client\Provider\GenericProvider;
use Square\SquareClient;
use Square\Environments;
use Square\OAuth\Requests\RevokeTokenRequest;


class AuthController extends AppController
{
    public function index(): Response
    {
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
            $authorizedMerchantId = env('SQUARE_AUTHORIZED_MERCHANT_ID');

            // Verify the merchant is authorized
            // We do this because we only want access to our client.
            if ($merchantId !== $authorizedMerchantId) {
                // Wrong merchant, reject
                $session->delete('oauth2state');
                $session->delete('oauth2pkceCode');
                throw new ForbiddenException(
                    'This Square account is not authorized to access this application.'
                );
            }

            // Correct merchant, store the token and its details in the session for later use
            $session->write('square_access_token', $accessToken->getToken());
            $session->write('square_token_payload', $accessToken->jsonSerialize());

            return $this->redirect(['action' => 'success']);
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
        $session = $this->request->getSession();
        $accessToken = $session->read('square_access_token');

        try {
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
            // Don't block logout if revocation fails - just log it
            \Cake\Log\Log::error('Failed to revoke Square token: ' . $e->getMessage());
        }

        // Clear all Square OAuth data from session
        $session->delete('square_access_token');
        $session->delete('square_token_payload');
        $session->delete('oauth2state');
        $session->delete('oauth2pkceCode');
        $session->delete('redirect_after_auth');

        // Destroy the entire session
        $session->destroy();

        return $this->redirect(['controller' => 'Auth', 'action' => 'index']);
    }

    public function success(): Response
    {
            // Redirect user back to originally requested URL if there was one
            $session = $this->request->getSession();
            $redirectUrl = $session->read('redirect_after_auth');
            $session->delete('redirect_after_auth');

            // Validate it's an internal URL
            if (!$redirectUrl || !str_starts_with($redirectUrl, '/')) {
                //If not or there was none, redirect home
                $redirectUrl = '/';
            }
            
            return $this->redirect($redirectUrl);
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
}
