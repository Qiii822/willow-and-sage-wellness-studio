<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Table\BookingAccessCodesTable;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Throwable;

/**
 * Finds a customer's appointments from nothing but their phone number.
 *
 * Two routes lead to the same person, and both matter: a booking made on
 * this site, which carries the number they typed, and one Wendy took over
 * the phone and entered in Square, which only exists as a Square customer.
 * Showing just the first would tell a regular that their next appointment
 * does not exist.
 */
class CustomerBookingLookup
{
    /** How many past appointments are listed - enough to book a favourite again. */
    public const PAST_LIMIT = 20;

    /**
     * What a past appointment that did not go ahead is called on the page.
     * One that did - accepted, or still pending when its day came - needs
     * no label.
     *
     * @var array<string, string>
     */
    protected const STATUS_LABELS = [
        'cancelled_by_customer' => 'Cancelled',
        'cancelled_by_seller' => 'Cancelled by the studio',
        'declined' => 'Declined',
        'no_show' => 'Missed',
    ];

    /** @var array<string, string>|null Therapist names, once fetched. */
    protected ?array $therapists = null;

    /**
     * Upcoming appointments for this number, soonest first.
     *
     * @param string $phone
     * @return array<int, array<string, mixed>>
     */
    public function upcoming(string $phone): array
    {
        return $this->find($phone, [
            'start_at >=' => DateTime::now(),
            /*
             * A whitelist, not a list of things to hide: Square has two
             * separate cancelled statuses and may grow more, and one
             * missed from a blacklist would show a customer an
             * appointment that is no longer happening.
             */
            'status IN' => ['accepted', 'pending'],
        ], 'ASC');
    }

    /**
     * Past appointments for this number, latest first.
     *
     * Cancelled and missed ones too, labelled, the way Square's own list of
     * a customer's appointments keeps them - "the facial I cancelled last
     * month" is as much something to book again as one that went ahead.
     *
     * @param string $phone
     * @return array<int, array<string, mixed>>
     */
    public function past(string $phone): array
    {
        return $this->find($phone, ['start_at <' => DateTime::now()], 'DESC', self::PAST_LIMIT);
    }

    /**
     * This number's appointments matching $where, ready for the page.
     *
     * @param string $phone
     * @param array<string, mixed> $where
     * @param string $order ASC or DESC by start time.
     * @param int|null $limit
     * @return array<int, array<string, mixed>>
     */
    protected function find(string $phone, array $where, string $order, ?int $limit = null): array
    {
        $locator = TableRegistry::getTableLocator();
        $digits = BookingAccessCodesTable::digits($phone);

        if ($digits === '') {
            return [];
        }

        $customerIds = $this->squareCustomerIds($digits);

        $conditions = [];

        if ($customerIds !== []) {
            $conditions[] = ['square_customer_id IN' => $customerIds];
        }

        /*
         * Drafts hold the number as typed, so they find a booking made
         * moments ago - before any sync has had a chance to run.
         */
        $draftBookingIds = $this->draftBookingIds($digits);

        if ($draftBookingIds !== []) {
            $conditions[] = ['square_booking_id IN' => $draftBookingIds];
        }

        if ($conditions === []) {
            return [];
        }

        $query = $locator->get('Bookings')->find()
            ->where(['OR' => $conditions] + $where)
            ->orderBy(['start_at' => $order]);

        if ($limit !== null) {
            $query->limit($limit);
        }

        $bookings = $query->all();

        if ($bookings->isEmpty()) {
            return [];
        }

        $drafts = $this->draftsByBookingId($digits);
        $names = $this->serviceNamesByVariation();
        $prices = $this->pricesByVariation();
        $menuIds = $this->menuIdsByVariation();
        $therapists = $this->therapistNames();

        $rows = [];

        foreach ($bookings as $booking) {
            $draft = $drafts[$booking->square_booking_id] ?? null;
            $segments = $this->segmentsOf($booking);
            $minutes = array_sum(array_map(
                fn(array $segment): int => (int)($segment['duration_minutes'] ?? $segment['durationMinutes'] ?? 0),
                $segments,
            ));

            $rows[] = [
                'square_booking_id' => $booking->square_booking_id,
                /*
                 * The sync writes this column in the server's own timezone
                 * (config/bootstrap.php pins that to Australia/Adelaide),
                 * so the value read back is already a correct instant and
                 * must not be relabelled - only converted for display.
                 */
                'start_at' => $booking->start_at,
                // Null when Square did not say how long it runs.
                'end_at' => $minutes > 0 ? $booking->start_at->addMinutes($minutes) : null,
                'minutes' => $minutes > 0 ? $minutes : null,
                /*
                 * What they were quoted, offer and all, for a booking made
                 * here; otherwise today's menu price for what was booked.
                 * Null when any part of it has no price to add up.
                 */
                'price_cents' => $draft
                    ? (int)$draft->payable_cents
                    : $this->priceOf($segments, $prices),
                'status' => $booking->status,
                'status_label' => self::STATUS_LABELS[$booking->status] ?? null,
                // A booking taken over the phone has no draft, so its
                // treatments are read back out of what Square stored.
                'treatments' => $draft
                    ? array_column($draft->items, 'name')
                    : $this->treatmentsFrom($segments, $names),
                'therapist' => $this->therapistOf($segments, $therapists),
                /*
                 * The same treatments as they are on today's menu, for
                 * "Book again". Matched by Square's variation id rather than
                 * copied from the draft, so a booking made over the phone
                 * can be repeated too, and a treatment since taken off the
                 * menu is simply left out instead of offered and refused.
                 */
                'rebook_service_ids' => array_values(array_unique(array_filter(array_map(
                    fn(array $segment): ?string => $menuIds[$this->variationOf($segment)] ?? null,
                    $segments,
                )))),
                'draft' => $draft,
            ];
        }

        return $rows;
    }

    /**
     * Square customer ids whose stored number matches.
     *
     * Compared in PHP rather than SQL because the stored numbers are not
     * written consistently - some carry +61, some a leading zero, some
     * spaces - and no single LIKE catches all of them.
     *
     * @param string $digits
     * @return array<int, string>
     */
    protected function squareCustomerIds(string $digits): array
    {
        $customers = TableRegistry::getTableLocator()->get('Customers')->find()
            ->select(['square_customer_id', 'phone_number'])
            ->where(['phone_number IS NOT' => null])
            ->disableHydration()
            ->all();

        $ids = [];

        foreach ($customers as $customer) {
            if (BookingAccessCodesTable::digits($customer['phone_number']) === $digits) {
                $ids[] = (string)$customer['square_customer_id'];
            }
        }

        return $ids;
    }

    /**
     * @param string $digits
     * @return array<int, string>
     */
    protected function draftBookingIds(string $digits): array
    {
        return array_keys($this->draftsByBookingId($digits));
    }

    /**
     * Confirmed drafts for this number, keyed by their Square booking id.
     *
     * @param string $digits
     * @return array<string, \App\Model\Entity\BookingDraft>
     */
    protected function draftsByBookingId(string $digits): array
    {
        $drafts = TableRegistry::getTableLocator()->get('BookingDrafts')->find()
            ->where([
                'status' => 'confirmed',
                'square_booking_id IS NOT' => null,
            ])
            ->all();

        $matched = [];

        foreach ($drafts as $draft) {
            if (BookingAccessCodesTable::digits($draft->customer_phone) === $digits) {
                $matched[(string)$draft->square_booking_id] = $draft;
            }
        }

        return $matched;
    }

    /**
     * Treatment names by the variation id Square books them as.
     *
     * @return array<string, string>
     */
    protected function serviceNamesByVariation(): array
    {
        $services = TableRegistry::getTableLocator()->get('Services')->find()
            ->select(['square_variation_id', 'name'])
            ->where(['square_variation_id IS NOT' => null])
            ->disableHydration()
            ->all();

        $names = [];

        foreach ($services as $service) {
            $names[(string)$service['square_variation_id']] = (string)$service['name'];
        }

        return $names;
    }

    /**
     * Menu prices by the variation id Square books them as.
     *
     * @return array<string, int|null>
     */
    protected function pricesByVariation(): array
    {
        $services = TableRegistry::getTableLocator()->get('Services')->find()
            ->select(['square_variation_id', 'price_cents'])
            ->where(['square_variation_id IS NOT' => null])
            ->disableHydration()
            ->all();

        $prices = [];

        foreach ($services as $service) {
            $prices[(string)$service['square_variation_id']] = $service['price_cents'] === null
                ? null
                : (int)$service['price_cents'];
        }

        return $prices;
    }

    /**
     * @param array<int, array<string, mixed>> $segments
     * @param array<string, int|null> $prices
     * @return int|null
     */
    protected function priceOf(array $segments, array $prices): ?int
    {
        $total = 0;

        foreach ($segments as $segment) {
            $price = $prices[$this->variationOf($segment)] ?? null;

            // One treatment priced on the day makes any total a guess.
            if ($price === null) {
                return null;
            }

            $total += $price;
        }

        return $segments === [] ? null : $total;
    }

    /**
     * Menu ids by variation, for the treatments that can be booked now.
     *
     * @return array<string, string>
     */
    protected function menuIdsByVariation(): array
    {
        $services = TableRegistry::getTableLocator()->get('Services')->find()
            ->select(['id', 'square_variation_id'])
            ->where(['square_variation_id IS NOT' => null, 'visible' => true])
            ->disableHydration()
            ->all();

        $ids = [];

        foreach ($services as $service) {
            $ids[(string)$service['square_variation_id']] = (string)$service['id'];
        }

        return $ids;
    }

    /**
     * Therapist names by Square team member id.
     *
     * Empty when Square cannot be reached: the name is a detail, and the
     * appointments are still worth showing without it.
     *
     * @return array<string, string>
     */
    protected function therapistNames(): array
    {
        // Asked once, though the page lists upcoming and past separately.
        if ($this->therapists !== null) {
            return $this->therapists;
        }

        try {
            $this->therapists = array_column((new SquareApiService())->listBookableTeamMembers(), 'name', 'id');
        } catch (Throwable $e) {
            Log::warning('Therapist names unavailable for My bookings: ' . $e->getMessage());

            $this->therapists = [];
        }

        return $this->therapists;
    }

    /**
     * The booking's segments as stored by the sync - one per treatment.
     *
     * @param \Cake\Datasource\EntityInterface $booking
     * @return array<int, array<string, mixed>>
     */
    protected function segmentsOf(EntityInterface $booking): array
    {
        $segments = $booking->appointment_segments;

        if (is_string($segments)) {
            $segments = json_decode($segments, true);
        }

        return array_values(array_filter((array)$segments, 'is_array'));
    }

    /**
     * @param array<string, mixed> $segment
     * @return string
     */
    protected function variationOf(array $segment): string
    {
        return (string)($segment['service_variation_id'] ?? $segment['serviceVariationId'] ?? '');
    }

    /**
     * @param array<int, array<string, mixed>> $segments
     * @param array<string, string> $names
     * @return array<int, string>
     */
    protected function treatmentsFrom(array $segments, array $names): array
    {
        $treatments = [];

        foreach ($segments as $segment) {
            $id = $this->variationOf($segment);

            if ($id !== '') {
                $treatments[] = $names[$id] ?? 'Treatment';
            }
        }

        return $treatments === [] ? ['Appointment'] : $treatments;
    }

    /**
     * Who is doing it - one name, or several when the treatments are split
     * between therapists.
     *
     * @param array<int, array<string, mixed>> $segments
     * @param array<string, string> $therapists
     * @return string|null
     */
    protected function therapistOf(array $segments, array $therapists): ?string
    {
        $names = [];

        foreach ($segments as $segment) {
            $id = (string)($segment['team_member_id'] ?? $segment['teamMemberId'] ?? '');

            if (isset($therapists[$id])) {
                $names[$therapists[$id]] = true;
            }
        }

        return $names === [] ? null : implode(' and ', array_keys($names));
    }
}
