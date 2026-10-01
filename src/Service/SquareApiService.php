<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use Throwable;
use Square\Bookings\Requests\CancelBookingRequest;
use Square\Bookings\Requests\CreateBookingRequest;
use Square\Bookings\Requests\GetBookingsRequest;
use Square\Bookings\Requests\ListBookingsRequest;
use Square\Bookings\Requests\SearchAvailabilityRequest;
use Square\Bookings\Requests\UpdateBookingRequest;
use Square\Catalog\Requests\ListCatalogRequest;
use Square\Customers\Requests\CreateCustomerRequest;
use Square\Customers\Requests\GetCustomersRequest;
use Square\Customers\Requests\ListCustomersRequest;
use Square\Customers\Requests\SearchCustomersRequest;
use Square\Environments;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Payments\Requests\GetPaymentsRequest;
use Square\Payments\Requests\ListPaymentsRequest;
use Square\Refunds\Requests\RefundPaymentRequest;
use Square\SquareClient;
use Square\Types\AppointmentSegment;
use Square\Types\Booking;
use Square\Types\CashPaymentDetails;
use Square\Types\CatalogItem;
use Square\Types\CatalogObject;
use Square\Types\Customer;
use Square\Types\CustomerFilter;
use Square\Types\CustomerQuery;
use Square\Types\CustomerTextFilter;
use Square\Types\DestinationDetailsCashRefundDetails;
use Square\Types\DestinationDetailsExternalRefundDetails;
use Square\Types\ExternalPaymentDetails;
use Square\Types\FilterValue;
use Square\Types\Location;
use Square\Types\Money;
use Square\Types\Payment;
use Square\Types\SearchAvailabilityFilter;
use Square\Types\SearchAvailabilityQuery;
use Square\Types\SegmentFilter;
use Square\Types\TimeRange;

/**
 * Makes calls to Square's API on behalf of the app's single, shared Square
 * connection.
 */
class SquareApiService
{
    /** Catalogue items that are bookable treatments rather than stock. */
    protected const SERVICE_PRODUCT_TYPE = 'APPOINTMENTS_SERVICE';

    /**
     * The widest range ListBookings will take. Square's own limit is 31
     * days; a day under it leaves room for the boundary arithmetic.
     */
    protected const BOOKING_WINDOW_DAYS = 30;

    /** Ways a booking can be paid for at the studio, as recorded in Square. */
    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'card' => 'Card (studio card reader)',
    ];

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
            throw new RuntimeException(
                'No active Square connection. An admin needs to connect Square before this action can be used.',
            );
        }

        $identitiesTable = TableRegistry::getTableLocator()->get('Identities');
        $accessToken = $identitiesTable->getDecryptedAccessToken($identity);

        if (!$accessToken) {
            throw new RuntimeException('No access token available on the active Square connection.');
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
            new GetCustomersRequest(['customerId' => $customerId]),
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
     * @return array<\Square\Types\Customer>
     */
    public function listCustomers(
        ?string $cursor = null,
        int $limit = 100,
        ?string $sortField = null,
        ?string $sortOrder = null,
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
     * @return array<\Square\Types\Booking>
     */
    public function listBookings(
        ?string $customerId = null,
        ?string $teamMemberId = null,
        ?string $locationId = null,
        ?string $startAtMin = null,
        ?string $startAtMax = null,
        int $limit = 100,
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
     * Every booking between two dates, however far apart they are.
     *
     * Two of Square's limits make this necessary rather than a convenience.
     * Asked for no range at all, ListBookings answers from now onwards, so
     * yesterday's appointments simply are not in the reply - which is how a
     * fully synced calendar can still come up empty for last Monday. And a
     * range may be at most 31 days, so a wider one has to be walked in
     * steps rather than asked for in one go.
     *
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     * @return array<\Square\Types\Booking>
     */
    public function listBookingsBetween(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $bookings = [];
        $seen = [];
        $windowStart = DateTimeImmutable::createFromInterface($from);
        $end = DateTimeImmutable::createFromInterface($to);

        while ($windowStart < $end) {
            $windowEnd = min($end, $windowStart->modify('+' . self::BOOKING_WINDOW_DAYS . ' days'));

            foreach (
                $this->listBookings(
                    null,
                    null,
                    null,
                    $windowStart->format(DATE_RFC3339),
                    $windowEnd->format(DATE_RFC3339),
                ) as $booking
            ) {
                $id = (string)$booking->getId();

                // Windows meet at their edges, so a booking sitting exactly
                // on a boundary can come back twice.
                if ($id !== '' && isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $bookings[] = $booking;
            }

            $windowStart = $windowEnd;
        }

        return $bookings;
    }

    /**
     * Fetches every payment, auto-paginating through all of Square's pages.
     *
     * @param string|null $beginTime RFC 3339 timestamp - defaults to one year ago on Square's side.
     * @param string|null $endTime RFC 3339 timestamp - defaults to now on Square's side.
     * @param string|null $locationId
     * @param int $limit
     * @return array<\Square\Types\Payment>
     */
    public function listPayments(
        ?string $beginTime = null,
        ?string $endTime = null,
        ?string $locationId = null,
        int $limit = 100,
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
     * Records a payment taken at the studio against a booking.
     *
     * No money moves through Square here - the customer has already paid in
     * cash or on the studio's own card reader. This writes the payment into
     * Square's books so its reports and this app's payments page both show
     * it, the same as a payment taken on a Square terminal would.
     *
     * The Square booking id goes in `reference_id`, which is how the booking
     * later knows it has been paid.
     *
     * @param string $bookingId Square booking id.
     * @param int $amountCents
     * @param string $method A key of PAYMENT_METHODS.
     * @param string|null $customerId Square customer id.
     * @param string|null $locationId
     * @param string|null $note
     * @param int $previousPayments How many payments the booking already has,
     *  undone ones included - so paying again after an undo is a new payment.
     * @return \Square\Types\Payment
     */
    public function recordBookingPayment(
        string $bookingId,
        int $amountCents,
        string $method,
        ?string $customerId = null,
        ?string $locationId = null,
        ?string $note = null,
        int $previousPayments = 0,
    ): Payment {
        if (!isset(self::PAYMENT_METHODS[$method])) {
            throw new RuntimeException('Unknown payment method: ' . $method);
        }

        $money = fn(): Money => new Money(['amount' => $amountCents, 'currency' => 'AUD']);

        $request = [
            /*
             * Keyed on the booking, amount and method, so a double click or
             * a resubmitted form gets the same payment back from Square
             * rather than recording the customer as having paid twice.
             *
             * The count of earlier payments is in it too. Without it, paying
             * again after an undo - same amount, same method - sends the same
             * key, and Square hands back the payment that was just refunded
             * instead of taking a new one.
             */
            'idempotencyKey' => substr(sha1(
                'booking-payment|' . $bookingId . '|' . $amountCents . '|' . $method . '|' . $previousPayments,
            ), 0, 40),
            'amountMoney' => $money(),
            'customerId' => $customerId,
            'locationId' => $locationId,
            'referenceId' => $bookingId,
            'note' => $note !== null && $note !== '' ? $note : null,
        ];

        if ($method === 'cash') {
            $request['sourceId'] = 'CASH';
            // Exact money handed over, so Square records no change given.
            $request['cashDetails'] = new CashPaymentDetails(['buyerSuppliedMoney' => $money()]);
        } else {
            $request['sourceId'] = 'EXTERNAL';
            $request['externalDetails'] = new ExternalPaymentDetails([
                'type' => 'CARD',
                'source' => 'Studio card reader',
            ]);
        }

        $payment = $this->getClient()->payments->create(new CreatePaymentRequest($request))->getPayment();

        if ($payment === null || $payment->getId() === null) {
            throw new RuntimeException('Square did not return a payment.');
        }

        return $payment;
    }

    /**
     * Undoes a booking payment that was recorded by mistake.
     *
     * Square will not delete or cancel a completed payment, so this refunds
     * all of it - Square's books then show it taken and given back, which
     * is what happened as far as they are concerned. Cash and studio card
     * reader payments refund the same way they were taken, outside Square.
     *
     * @param string $paymentId Square payment id.
     * @param int $amountCents The full amount of the payment.
     * @param string|null $sourceType Square's source type for it - CASH or EXTERNAL.
     * @return \Square\Types\Payment The payment as it stands after the refund.
     */
    public function undoBookingPayment(string $paymentId, int $amountCents, ?string $sourceType): Payment
    {
        $money = fn(): Money => new Money(['amount' => $amountCents, 'currency' => 'AUD']);

        $request = [
            // One undo per payment, however many times the button is pressed.
            'idempotencyKey' => substr(sha1('booking-payment-undo|' . $paymentId), 0, 40),
            'amountMoney' => $money(),
            'paymentId' => $paymentId,
            'reason' => 'Recorded by mistake - undone from the booking page',
        ];

        if ($sourceType === 'CASH') {
            $request['cashDetails'] = new DestinationDetailsCashRefundDetails(['sellerSuppliedMoney' => $money()]);
        } elseif ($sourceType === 'EXTERNAL') {
            $request['externalDetails'] = new DestinationDetailsExternalRefundDetails([
                'type' => 'CARD',
                'source' => 'Studio card reader',
            ]);
        }

        $this->getClient()->refunds->refundPayment(new RefundPaymentRequest($request));

        return $this->getPayment($paymentId);
    }

    /**
     * Fetches one payment as Square has it now.
     *
     * @param string $paymentId
     * @return \Square\Types\Payment
     */
    public function getPayment(string $paymentId): Payment
    {
        $payment = $this->getClient()->payments->get(new GetPaymentsRequest(['paymentId' => $paymentId]))->getPayment();

        if ($payment === null) {
            throw new RuntimeException('Square did not return the payment.');
        }

        return $payment;
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
            $variationId = null;

            $variations = $item->getVariations() ?? [];

            if ($variations !== []) {
                /*
                 * The first variation is the bookable one for a service with
                 * a single option, and its id is what availability searches
                 * and CreateBooking are keyed on.
                 */
                $variationId = $variations[0]->jsonSerialize()['id'] ?? null;

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
                'square_variation_id' => $variationId,
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
     * The studio's location: the id every booking is made against, and the
     * timezone its opening hours are expressed in.
     *
     * The timezone is read from Square rather than hard-coded, because it
     * is Square's own answer to "what time is it at the studio" - the same
     * answer its calendar and its staff app use. Hard-coding one means
     * every booking silently shifts if the studio's setting ever differs.
     *
     * @return array{id: string, name: string, timezone: string, longest_day_minutes: int|null}
     * @throws \RuntimeException When Square reports no location.
     */
    public function getPrimaryLocation(): array
    {
        $response = $this->getClient()->locations->list();

        foreach ($response->getLocations() ?? [] as $location) {
            return [
                'id' => (string)$location->getId(),
                'name' => (string)$location->getName(),
                // Falls back to the studio's own state rather than to UTC,
                // which would be wrong by a whole working day's offset.
                'timezone' => $location->getTimezone() ?: 'Australia/Adelaide',
                'longest_day_minutes' => $this->longestOpenDay($location),
            ];
        }

        throw new RuntimeException('Square reports no location for this account.');
    }

    /**
     * The longest open day, cached, and never a reason for a page to fail.
     *
     * The treatment menu needs this to warn that a cart cannot fit in one
     * day, and that page otherwise touches nothing but the local database.
     * So the answer is cached - opening hours change about once a year -
     * and a Square that is slow or down gives null, which means the warning
     * is skipped rather than the menu breaking.
     *
     * @return int|null
     */
    public function longestOpenDayMinutes(): ?int
    {
        return Cache::remember('square_longest_open_day', function (): ?int {
            try {
                return $this->getPrimaryLocation()['longest_day_minutes'];
            } catch (Throwable $e) {
                return null;
            }
        });
    }

    /**
     * The longest single day the studio is open, in minutes.
     *
     * Used to tell "no times left this month" apart from "this can never
     * fit in one day" - a booking longer than the longest opening cannot be
     * made at all, and the customer deserves to be told that rather than to
     * keep pressing the next-month arrow.
     *
     * Null when Square has no opening hours set, in which case nothing can
     * be concluded and the caller should not guess.
     *
     * @param \Square\Types\Location $location
     */
    protected function longestOpenDay(Location $location): ?int
    {
        $periods = $location->getBusinessHours()?->getPeriods() ?? [];
        $longest = null;

        foreach ($periods as $period) {
            $start = $period->getStartLocalTime();
            $end = $period->getEndLocalTime();

            if ($start === null || $end === null) {
                continue;
            }

            $minutes = $this->minutesOfDay($end) - $this->minutesOfDay($start);

            if ($minutes > 0) {
                $longest = max($longest ?? 0, $minutes);
            }
        }

        return $longest;
    }

    /**
     * "09:00:00" as minutes since midnight.
     *
     * @param string $localTime
     * @return int
     */
    protected function minutesOfDay(string $localTime): int
    {
        [$hours, $minutes] = array_pad(explode(':', $localTime), 2, '0');

        return ((int)$hours * 60) + (int)$minutes;
    }

    /**
     * The therapists a customer may book, as Square lists them.
     *
     * Only the bookable ones: a team member who takes payments but does no
     * treatments has no place on a page asking who should do yours.
     *
     * @return array<int, array{id: string, name: string, description: string|null, image_url: string|null}>
     */
    public function listBookableTeamMembers(): array
    {
        $members = [];

        foreach ($this->getClient()->bookings->teamMemberProfiles->list() as $profile) {
            if ($profile->getIsBookable() !== true) {
                continue;
            }

            $id = (string)$profile->getTeamMemberId();

            if ($id === '') {
                continue;
            }

            $members[] = [
                'id' => $id,
                // Square lets the name go unset; a blank card would be
                // unpickable, so it falls back to something sayable.
                'name' => (string)($profile->getDisplayName() ?: 'Our therapist'),
                'description' => $profile->getDescription() ?: null,
                'image_url' => $profile->getProfileImageUrl() ?: null,
            ];
        }

        usort($members, fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $members;
    }

    /**
     * Open appointment slots for a set of treatments.
     *
     * With no team member named, Square answers with whoever is free and
     * tells us who that is - which is exactly what CreateBooking then needs.
     * Naming one narrows the answer to that person's own diary, which is
     * what a customer who asked for them by name expects to see.
     *
     * Several variation ids book them back to back as one appointment,
     * which is how a customer choosing two treatments should be handled.
     *
     * @param array<int, string> $variationIds
     * @param \DateTimeInterface $from
     * @param \DateTimeInterface $to
     * @param string|null $teamMemberId Null means anyone available.
     * @return array<int, array{start_at: string, segments: array<int, array{team_member_id: string, service_variation_id: string, duration_minutes: int|null, service_variation_version: int|null}>}>
     */
    public function searchAvailability(
        array $variationIds,
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?string $teamMemberId = null,
    ): array {
        if ($variationIds === []) {
            return [];
        }

        $location = $this->getPrimaryLocation();

        $memberFilter = $teamMemberId === null || $teamMemberId === ''
            ? null
            : new FilterValue(['any' => [$teamMemberId]]);

        $segmentFilters = array_map(
            fn(string $id): SegmentFilter => new SegmentFilter([
                'serviceVariationId' => $id,
                'teamMemberIdFilter' => $memberFilter,
            ]),
            array_values($variationIds),
        );

        $response = $this->getClient()->bookings->searchAvailability(
            new SearchAvailabilityRequest([
                'query' => new SearchAvailabilityQuery([
                    'filter' => new SearchAvailabilityFilter([
                        'startAtRange' => new TimeRange([
                            'startAt' => $from->format(DATE_RFC3339),
                            'endAt' => $to->format(DATE_RFC3339),
                        ]),
                        'locationId' => $location['id'],
                        'segmentFilters' => $segmentFilters,
                    ]),
                ]),
            ]),
        );

        $slots = [];

        foreach ($response->getAvailabilities() ?? [] as $availability) {
            $segments = [];

            foreach ($availability->getAppointmentSegments() ?? [] as $segment) {
                $segments[] = [
                    'team_member_id' => (string)$segment->getTeamMemberId(),
                    'service_variation_id' => (string)$segment->getServiceVariationId(),
                    'duration_minutes' => $segment->getDurationMinutes(),
                    /*
                     * Carried through because CreateBooking is rejected if
                     * the catalogue has moved on since the slot was offered.
                     */
                    'service_variation_version' => $segment->getServiceVariationVersion(),
                ];
            }

            $slots[] = [
                // RFC 3339 in UTC; callers convert for display.
                'start_at' => (string)$availability->getStartAt(),
                'segments' => $segments,
            ];
        }

        return $slots;
    }

    /**
     * Finds the customer with this email, or creates one.
     *
     * Matched on email because that is what a returning customer types the
     * same way twice; a phone number arrives as +61, 04, or with spaces,
     * and treating those as different people would fill the studio's list
     * with duplicates of the same regular.
     *
     * @param string $name
     * @param string $email
     * @param string $phone
     * @return string The Square customer id.
     */
    public function findOrCreateCustomer(string $name, string $email, string $phone): string
    {
        $client = $this->getClient();

        $existing = $client->customers->search(new SearchCustomersRequest([
            'limit' => 1,
            'query' => new CustomerQuery([
                'filter' => new CustomerFilter([
                    'emailAddress' => new CustomerTextFilter(['exact' => $email]),
                ]),
            ]),
        ]));

        foreach ($existing->getCustomers() ?? [] as $customer) {
            return (string)$customer->getId();
        }

        // Split on the last space, so "Anna Maria Lee" keeps its given names
        // together rather than losing everything after the first word.
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $surname = count($parts) > 1 ? (string)array_pop($parts) : '';
        $given = implode(' ', $parts);

        $created = $client->customers->create(new CreateCustomerRequest([
            'idempotencyKey' => Text::uuid(),
            'givenName' => $given !== '' ? $given : $name,
            'familyName' => $surname !== '' ? $surname : null,
            'emailAddress' => $email,
            'phoneNumber' => $phone !== '' ? $phone : null,
        ]));

        $customer = $created->getCustomer();

        if ($customer === null || $customer->getId() === null) {
            throw new RuntimeException('Square did not return a customer.');
        }

        return (string)$customer->getId();
    }

    /**
     * Writes a booking into the studio's Square calendar.
     *
     * The segments come straight from the availability search rather than
     * being rebuilt here: they carry the team member Square chose and the
     * catalogue version the slot was offered against, and Square rejects a
     * booking whose version has moved on.
     *
     * @param string $customerId
     * @param string $startAt RFC 3339, UTC.
     * @param array<int, array<string, mixed>> $segments
     * @param string|null $note What the customer told us.
     * @return string The Square booking id.
     */
    public function createBooking(
        string $customerId,
        string $startAt,
        array $segments,
        ?string $note = null,
    ): string {
        $location = $this->getPrimaryLocation();

        $appointmentSegments = array_map(
            fn(array $segment): AppointmentSegment => new AppointmentSegment([
                'teamMemberId' => (string)$segment['team_member_id'],
                'serviceVariationId' => (string)$segment['service_variation_id'],
                'serviceVariationVersion' => $segment['service_variation_version'] ?? null,
                'durationMinutes' => $segment['duration_minutes'] ?? null,
            ]),
            $segments,
        );

        $response = $this->getClient()->bookings->create(new CreateBookingRequest([
            /*
             * Keyed on the draft plus the time, so a customer who submits
             * twice - a double click, a retry after a slow response - gets
             * the same booking back rather than two.
             */
            'idempotencyKey' => substr(sha1($customerId . $startAt), 0, 40),
            'booking' => new Booking([
                'locationId' => $location['id'],
                'customerId' => $customerId,
                'startAt' => $startAt,
                'appointmentSegments' => $appointmentSegments,
                'customerNote' => $note !== null && $note !== '' ? $note : null,
            ]),
        ]));

        $booking = $response->getBooking();

        if ($booking === null || $booking->getId() === null) {
            throw new RuntimeException('Square did not return a booking.');
        }

        return (string)$booking->getId();
    }

    /**
     * Fetches one booking.
     *
     * @param string $bookingId
     * @return \Square\Types\Booking|null
     */
    public function getBooking(string $bookingId): ?Booking
    {
        return $this->getClient()->bookings->get(
            new GetBookingsRequest(['bookingId' => $bookingId]),
        )->getBooking();
    }

    /**
     * Cancels a booking.
     *
     * Square wants the version it last wrote, so a booking that changed
     * since we read it is refused rather than silently overwritten - which
     * is what should happen if the studio has already moved it.
     *
     * @param string $bookingId
     * @return void
     */
    public function cancelBooking(string $bookingId): void
    {
        $booking = $this->getBooking($bookingId);

        if ($booking === null) {
            throw new RuntimeException('That booking no longer exists.');
        }

        $this->getClient()->bookings->cancel(new CancelBookingRequest([
            'bookingId' => $bookingId,
            'bookingVersion' => $booking->getVersion(),
            'idempotencyKey' => Text::uuid(),
        ]));
    }

    /**
     * Moves a booking to a different time.
     *
     * Only the time is sent. Square refuses to take appointment_segments on
     * an update without APPOINTMENTS_ALL_WRITE - a scope that would let this
     * app rewrite every booking in the business, including ones it never
     * made - so the treatments stay exactly as they were booked and the new
     * time has to be one the same team member is free for.
     *
     * @param string $bookingId
     * @param string $startAt RFC 3339, UTC.
     * @return void
     */
    public function rescheduleBooking(string $bookingId, string $startAt): void
    {
        $existing = $this->getBooking($bookingId);

        if ($existing === null) {
            throw new RuntimeException('That booking no longer exists.');
        }

        $this->getClient()->bookings->update(new UpdateBookingRequest([
            'bookingId' => $bookingId,
            'idempotencyKey' => Text::uuid(),
            'booking' => new Booking([
                'version' => $existing->getVersion(),
                'startAt' => $startAt,
            ]),
        ]));
    }

    /**
     * The team members an existing booking is assigned to.
     *
     * @param string $bookingId
     * @return array<int, string>
     */
    public function bookingTeamMemberIds(string $bookingId): array
    {
        $booking = $this->getBooking($bookingId);

        if ($booking === null) {
            return [];
        }

        $ids = array_map(
            fn(AppointmentSegment $segment): string => (string)$segment->getTeamMemberId(),
            $booking->getAppointmentSegments() ?? [],
        );

        return array_values(array_unique(array_filter($ids)));
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
