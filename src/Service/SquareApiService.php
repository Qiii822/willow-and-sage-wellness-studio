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
use Square\Catalog\Requests\ListCatalogRequest;
use Square\Types\CatalogItem;
use Square\Types\CatalogObject;

/**
 * Makes calls to Square's API on behalf of the app's single, shared Square
 * connection.
 */
class SquareApiService
{
    /** Catalogue items that are bookable treatments rather than stock. */
    protected const SERVICE_PRODUCT_TYPE = 'APPOINTMENTS_SERVICE';

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

    /**
     * Fetches the bookable treatments from Square's catalogue.
     *
     * The catalogue holds retail stock and bookable services side by side,
     * so anything that is not an APPOINTMENTS_SERVICE is left out - without
     * that filter a jar of face oil would arrive on the treatment menu.
     *
     * Price and duration live on the item's first variation rather than on
     * the item, which is where Square puts them for a service with a single
     * bookable option.
     *
     * @return array<int, array{square_catalog_id: string, name: string, description: string|null, category: string|null, price_cents: int|null, duration_minutes: int|null}>
     */
    public function listServiceItems(): array
    {
        $client = $this->getClient();

        $categories = $this->listCatalogCategories($client);
        $services = [];

        $pager = $client->catalog->list(new ListCatalogRequest(['types' => 'ITEM']));

        foreach ($pager as $object) {
            if (!$object->isItem()) {
                continue;
            }

            // asItem() returns the wrapper; the fields are one level down.
            $item = $object->asItem()->getItemData();

            if ($item === null || $item->getProductType() !== self::SERVICE_PRODUCT_TYPE) {
                continue;
            }

            $priceCents = null;
            $durationMinutes = null;

            $variations = $item->getVariations() ?? [];

            if ($variations !== []) {
                $variation = $variations[0]->asItemVariation()->getItemVariationData();

                if ($variation !== null) {
                    $money = $variation->getPriceMoney();

                    // A variable-priced treatment has no amount, which the
                    // site renders as "Price varies" rather than as zero.
                    $priceCents = $money?->getAmount();

                    $duration = $variation->getServiceDuration();

                    // Square counts service time in milliseconds.
                    $durationMinutes = $duration === null
                        ? null
                        : (int)round($duration / 60000);
                }
            }

            $services[] = [
                'square_catalog_id' => $this->catalogObjectId($object),
                'name' => (string)$item->getName(),
                'description' => $item->getDescription(),
                'category' => $this->categoryNameFor($item, $categories),
                'price_cents' => $priceCents,
                'duration_minutes' => $durationMinutes,
            ];
        }

        return $services;
    }

    /**
     * The names of every category defined in Square's catalogue.
     *
     * Separate from the treatments because a category can exist in Square
     * with nothing filed under it yet, and the site's own list should still
     * offer it.
     *
     * @return array<int, string>
     */
    public function listServiceCategoryNames(): array
    {
        return array_values($this->listCatalogCategories($this->getClient()));
    }

    /**
     * Category id to name, so items can carry a readable category rather
     * than the id Square references them by.
     *
     * @param \Square\SquareClient $client
     * @return array<string, string>
     */
    protected function listCatalogCategories(SquareClient $client): array
    {
        $categories = [];

        $pager = $client->catalog->list(new ListCatalogRequest(['types' => 'CATEGORY']));

        foreach ($pager as $object) {
            if (!$object->isCategory()) {
                continue;
            }

            $name = $object->asCategory()->getCategoryData()?->getName();

            if ($name !== null && $name !== '') {
                $categories[$this->catalogObjectId($object)] = $name;
            }
        }

        return $categories;
    }

    /**
     * The Square id of a catalogue object.
     *
     * CatalogObject is modelled as a union of type and value, and the id is
     * not on either - the SDK only reassembles it when the object is
     * serialised. Reading it back out of that is the one route the SDK
     * actually offers, so it is done here rather than repeated at each call
     * site.
     *
     * @param \Square\Types\CatalogObject $object
     * @return string
     */
    protected function catalogObjectId(CatalogObject $object): string
    {
        return (string)($object->jsonSerialize()['id'] ?? '');
    }

    /**
     * The category name for one item.
     *
     * Square says this three different ways and an item can carry any of
     * them, so all three are tried in the order the dashboard prefers:
     *
     * - `reporting_category`, which is the "Reporting category" column the
     *   seller actually sees and sets;
     * - `categories`, the newer list an item can belong to;
     * - `category_id`, the single older field.
     *
     * Reading only the last two is why a menu can come through correctly
     * filed in Square and completely unfiled here.
     *
     * Null when the studio has not put the treatment under anything.
     *
     * @param \Square\Types\CatalogItem $item
     * @param array<string, string> $categories Category id to name.
     * @return string|null
     */
    protected function categoryNameFor(CatalogItem $item, array $categories): ?string
    {
        $reporting = $item->getReportingCategory();

        if ($reporting !== null) {
            /*
             * Usually just a reference, so the name comes from the lookup.
             * When Square does inline the whole object, that is used
             * directly rather than needing the id to resolve.
             */
            $inline = $reporting->getCategoryData()?->getName();

            if ($inline !== null && $inline !== '') {
                return $inline;
            }

            $id = (string)($reporting->jsonSerialize()['id'] ?? '');

            if (isset($categories[$id])) {
                return $categories[$id];
            }
        }

        foreach ($item->getCategories() ?? [] as $assigned) {
            $id = (string)$assigned->getId();

            if (isset($categories[$id])) {
                return $categories[$id];
            }
        }

        $legacy = $item->getCategoryId();

        return $legacy !== null ? ($categories[$legacy] ?? null) : null;
    }
}
