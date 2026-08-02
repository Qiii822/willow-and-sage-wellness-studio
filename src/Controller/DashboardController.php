<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

class DashboardController extends AppController
{
    public function index(): Response
    {
        $session = $this->request->getSession();
        $identityId = $session->read('identity_id');
        $merchantId = $session->read('merchant_id');

        if (!$identityId || !$merchantId) {
            // This shouldn't happen if middleware is working, but be safe
            return $this->redirect(['controller' => 'Auth', 'action' => 'login']);
        }

        // Get the identity from database (without exposing tokens)
        $identitiesTable = $this->fetchTable('Identities');
        $identity = $identitiesTable->get($identityId);

        if (!$identity || $identity->revoked) {
            // Identity was revoked
            return $this->redirect(['controller' => 'Auth', 'action' => 'logout']);
        }

        $this->set('merchantId', $merchantId);
        $this->set('createdAt', $identity->created_at);
        $this->set('expiresAt', $identity->expires_at);

        return $this->render();
    }
}