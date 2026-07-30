<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use League\OAuth2\Client\Provider\GenericProvider;

class AuthController extends AppController
{
    public function index(): Response
    {
        return $this->render();
    }

    public function login(): Response
    {
        $provider = $this->createProvider();
        $scope = env('SQUARE_SCOPE', 'MERCHANT_PROFILE_READ');

        $authorizationUrl = $provider->getAuthorizationUrl([
            'scope' => $scope,
        ]);

        $this->request->getSession()->write('oauth2state', $provider->getState());
        $this->request->getSession()->write('oauth2pkceCode', $provider->getPkceCode());

        return $this->redirect($authorizationUrl);
    }

    public function callback(): Response
    {
        $provider = $this->createProvider();
        $session = $this->request->getSession();

        $code = (string)$this->request->getQuery('code');
        $state = (string)$this->request->getQuery('state');
        $expectedState = (string)$session->read('oauth2state');
        $pkceCode = (string)$session->read('oauth2pkceCode');

        if ($state === '' || $state !== $expectedState) {
            throw new \UnexpectedValueException('Invalid OAuth state');
        }

        $provider->setPkceCode($pkceCode);

        $token = $provider->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        $session->write('square_access_token', $token->getToken());

        return $this->redirect('/');
    }

    private function createProvider(): GenericProvider
    {
        return new GenericProvider([
            'clientId' => env('SQUARE_CLIENT_ID', ''),
            'clientSecret' => env('SQUARE_CLIENT_SECRET', ''),
            'redirectUri' => env('SQUARE_REDIRECT_URI', ''),
            'urlAuthorize' => 'https://connect.squareupsandbox.com/oauth2/authorize',
            'urlAccessToken' => env('SQUARE_ACCES_TOKEN', ''),
            'urlResourceOwnerDetails' => 'https://service.example.com/resource',
            'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
        ]);
    }
}
