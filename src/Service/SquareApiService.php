<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Square\SquareClient;
use Square\Environments;
use Square\Types\Customer;
use Square\Customers\Requests\ListCustomersRequest;
use Square\Customers\Requests\GetCustomersRequest;

/**
 * Makes calls to Square's API on behalf of the app's single, shared Square
 * connection.
 */
class SquareApiService
{
    protected SquareAuthService $squareAuth;

    public function __construct()
    {
        $this->squareAuth = new SquareAuthService();
    }

    /**
     * Builds an authenticated Square client using the app's currently
     * active Square connection.
     *
     * @return \Square\SquareClient
     * @throws \RuntimeException If Square has not been connected, or the
     *  connection has been revoked.
     */
    protected function getClient(): SquareClient
    {
        $identity = $this->squareAuth->getActiveIdentity();

        if (!$identity) {
            throw new \RuntimeException(
                'No active Square connection. An admin needs to connect Square before this action can be used.'
            );
        }

        $identitiesTable = TableRegistry::getTableLocator()->get('Identities');
        $accessToken = $identitiesTable->getDecryptedAccessToken($identity);

        if (!$accessToken) {
            throw new \RuntimeException('No access token available on the active Square connection.');
        }

        $environment = Configure::read('debug')
            ? Environments::Sandbox
            : Environments::Production;

        return new SquareClient(
            token: $accessToken,
            options: ['baseUrl' => $environment->value],
        );
    }

    /**
     * Fetches a single customer by their Square customer ID.
     *
     * @param string $customerId
     * @return \Square\Types\Customer|null
     */
    public function getCustomerById(string $customerId): ?Customer
    {
        $client = $this->getClient();

        $response = $client->customers->get(
            new GetCustomersRequest(['customerId' => $customerId])
        );

        return $response?->getCustomer();
    }

    /**
     * Fetches every customer, auto-paginating through all of Square's pages.
     *
     * @param string|null $cursor
     * @param int $limit
     * @param string|null $sortField
     * @param string|null $sortOrder
     * @return \Square\Types\Customer[]
     */
    public function listCustomers(
        ?string $cursor = null,
        int $limit = 100,
        ?string $sortField = null,
        ?string $sortOrder = null
    ): array {
        $client = $this->getClient();

        $request = new ListCustomersRequest([
            'cursor' => $cursor,
            'limit' => $limit,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
        ]);

        $pager = $client->customers->list($request);

        // Pager::getIterator() yields Customer objects one at a time,
        // fetching additional pages from Square automatically as needed.
        return iterator_to_array($pager);
    }
}