<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use App\Service\SquareApiService;

class CustomersController extends AppController
{
    public function index(): Response
    {
        $session = $this->request->getSession();
        $identityId = $session->read('identity_id');

        if (!$identityId) {
            // Shouldn't happen if middleware is working, but be safe
            return $this->redirect(['controller' => 'Auth', 'action' => 'login']);
        }

        $identitiesTable = $this->fetchTable('Identities');
        $identity = $identitiesTable->get($identityId);

        if (!$identity || $identity->revoked) {
            return $this->redirect(['controller' => 'Auth', 'action' => 'logout']);
        }

        // Optional query params for quick manual testing, e.g.
        // /customers?limit=10&sortField=CREATED_AT&sortOrder=DESC
        $limit = (int)$this->request->getQuery('limit', 100);
        $sortField = $this->request->getQuery('sortField');
        $sortOrder = $this->request->getQuery('sortOrder');
        $cursor = $this->request->getQuery('cursor');

        $squareApi = new SquareApiService();

        try {
            $customers = $squareApi->listCustomers(
                $identity,
                $cursor,
                $limit,
                $sortField,
                $sortOrder
            );
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('Failed to list customers: ' . $e->getMessage());
            $this->Flash->error('Could not load customers from Square: ' . $e->getMessage());
            $customers = [];
        }

        $this->set('customers', $customers);

        return $this->render();
    }

    public function view(string $id): Response
    {
        $session = $this->request->getSession();
        $identityId = $session->read('identity_id');

        if (!$identityId) {
            return $this->redirect(['controller' => 'Auth', 'action' => 'login']);
        }

        $identitiesTable = $this->fetchTable('Identities');
        $identity = $identitiesTable->get($identityId);

        if (!$identity || $identity->revoked) {
            return $this->redirect(['controller' => 'Auth', 'action' => 'logout']);
        }

        $squareApi = new SquareApiService();

        try {
            $customer = $squareApi->getCustomerById($identity, $id);
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('Failed to get customer: ' . $e->getMessage());
            $this->Flash->error('Could not load customer from Square: ' . $e->getMessage());
            $customer = null;
        }

        $this->set('customer', $customer);

        return $this->render();
    }
}