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
use Square\Bookings\Requests\ListBookingsRequest;
use Square\Payments\Requests\ListPaymentsRequest;

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

    /**
     * Fetches every booking, auto-paginating through all of Square's pages.
     *
     * @param string|null $customerId
     * @param string|null $teamMemberId
     * @param string|null $locationId
     * @param string|null $startAtMin
     * @param string|null $startAtMax
     * @param int $limit
     * @return \Square\Types\Booking[]
     */
    public function listBookings(
        ?string $customerId = null,
        ?string $teamMemberId = null,
        ?string $locationId = null,
        ?string $startAtMin = null,
        ?string $startAtMax = null,
        int $limit = 100
    ): array {
        $client = $this->getClient();

        $request = new ListBookingsRequest([
            'limit' => $limit,
            'customerId' => $customerId,
            'teamMemberId' => $teamMemberId,
            'locationId' => $locationId,
            'startAtMin' => $startAtMin,
            'startAtMax' => $startAtMax,
        ]);

        $pager = $client->bookings->list($request);

        // Pager::getIterator() yields Booking objects one at a time,
        // fetching additional pages from Square automatically as needed.
        return iterator_to_array($pager);
    }

    /**
     * Fetches every payment, auto-paginating through all of Square's pages.
     *
     * @param string|null $beginTime RFC 3339 timestamp - defaults to one year ago on Square's side.
     * @param string|null $endTime RFC 3339 timestamp - defaults to now on Square's side.
     * @param string|null $locationId
     * @param int $limit
     * @return \Square\Types\Payment[]
     */
    public function listPayments(
        ?string $beginTime = null,
        ?string $endTime = null,
        ?string $locationId = null,
        int $limit = 100
    ): array {
        $client = $this->getClient();

        $request = new ListPaymentsRequest([
            'beginTime' => $beginTime,
            'endTime' => $endTime,
            'locationId' => $locationId,
            'limit' => $limit,
        ]);

        $pager = $client->payments->list($request);

        // Pager::getIterator() yields Payment objects one at a time,
        // fetching additional pages from Square automatically as needed.
        return iterator_to_array($pager);
    }
}
