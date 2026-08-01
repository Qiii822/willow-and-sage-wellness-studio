<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\Http\Exception\ForbiddenException;
use League\OAuth2\Client\Provider\GenericProvider;

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

        if ( !env('DEBUG') ) {
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

    public function success(): Response
    {
        // Show the token details on a simple success page after the exchange succeeds.
        $session = $this->request->getSession();
        $this->set('accessToken', $session->read('square_access_token'));
        $this->set('tokenPayload', $session->read('square_token_payload'));

        return $this->render();
    }

    private function createProvider(): GenericProvider
    {
        // Configure the provider with the Square sandbox endpoints and PKCE support.
        return new GenericProvider([
            'clientId' => env('SQUARE_CLIENT_ID', ''),
            'redirectUri' => env('SQUARE_REDIRECT_URI', ''),
            'urlAuthorize' => 'https://connect.squareupsandbox.com/oauth2/authorize',
            'urlAccessToken' => 'https://connect.squareupsandbox.com/oauth2/token',
            'urlResourceOwnerDetails' => 'https://connect.squareupsandbox.com/v2/merchants/me',
            'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
            'scopeSeparator' => ' ', 
        ]);
    }
}
