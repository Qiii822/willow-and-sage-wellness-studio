<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;
use Square\SquareClient;
use Square\Environments;
use Square\Customers\Requests\ListCustomersRequest;
use Square\Customers\Requests\GetCustomersRequest;

class SquareApiService
{
    protected \Cake\ORM\Table $identitiesTable;

    public function __construct()
    {
        $this->identitiesTable = TableRegistry::getTableLocator()->get('Identities');
    }

    protected function getClientForIdentity(EntityInterface $identity): SquareClient
    {
        $accessToken = $this->identitiesTable->getDecryptedAccessToken($identity);

        if (!$accessToken) {
            throw new \RuntimeException('No access token available');
        }

        $environment = Configure::read('debug')
            ? Environments::Sandbox
            : Environments::Production;

        return new SquareClient(
            token: $accessToken,
            options: ['baseUrl' => $environment->value],
        );
    }

    public function getCustomerById(EntityInterface $identity, string $customerId): ?\Square\Types\Customer
    {
        $client = $this->getClientForIdentity($identity);

        $response = $client->customers->get(
            new GetCustomersRequest(['customerId' => $customerId])
        );

        return $response?->getCustomer();
    }

    public function listCustomers(
        EntityInterface $identity,
        ?string $cursor = null,
        int $limit = 100,
        ?string $sortField = null,
        ?string $sortOrder = null
    ): array {
        $client = $this->getClientForIdentity($identity);

        $request = new ListCustomersRequest([
            'cursor' => $cursor,
            'limit' => $limit,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
        ]);

        $pager = $client->customers->list($request);

        // Pager::getIterator() yields Customer objects one at a time,
        // fetching additional pages from Square automatically as needed
        return iterator_to_array($pager);
    }
}
