<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

class DashboardController extends AppController
{
    public function index(): Response
    {
        // Get the logged-in user's data from session
        $accessToken = $this->request->getSession()->read('square_access_token');
        $tokenPayload = $this->request->getSession()->read('square_token_payload');
        
        $this->set('accessToken', $accessToken);
        $this->set('tokenPayload', $tokenPayload);
        
        return $this->render();
    }
}