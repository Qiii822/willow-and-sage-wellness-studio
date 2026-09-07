<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Table\BookingAccessCodesTable;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

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
    /**
     * Upcoming appointments for this number, soonest first.
     *
     * @param string $phone
     * @return array<int, array<string, mixed>>
     */
    public function upcoming(string $phone): array
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

        $bookings = $locator->get('Bookings')->find()
            ->where([
                'OR' => $conditions,
                'start_at >=' => DateTime::now(),
                /*
                 * A whitelist, not a list of things to hide: Square has two
                 * separate cancelled statuses and may grow more, and one
                 * missed from a blacklist would show a customer an
                 * appointment that is no longer happening.
                 */
                'status IN' => ['accepted', 'pending'],
            ])
            ->orderByAsc('start_at')
            ->all();

        $drafts = $this->draftsByBookingId($digits);
        $names = $this->serviceNamesByVariation();

        $rows = [];

        foreach ($bookings as $booking) {
            $draft = $drafts[$booking->square_booking_id] ?? null;

            $rows[] = [
                'square_booking_id' => $booking->square_booking_id,
                /*
                 * The sync writes this column in the server's own timezone
                 * (config/bootstrap.php pins that to Australia/Melbourne),
                 * so the value read back is already a correct instant and
                 * must not be relabelled - only converted for display.
                 */
                'start_at' => $booking->start_at,
                'status' => $booking->status,
                // A booking taken over the phone has no draft, so its
                // treatments are read back out of what Square stored.
                'treatments' => $draft
                    ? array_column($draft->items, 'name')
                    : $this->treatmentsFrom($booking, $names),
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
     * @param \Cake\Datasource\EntityInterface $booking
     * @param array<string, string> $names
     * @return array<int, string>
     */
    protected function treatmentsFrom(EntityInterface $booking, array $names): array
    {
        $segments = $booking->appointment_segments;

        if (is_string($segments)) {
            $segments = json_decode($segments, true);
        }

        $treatments = [];

        foreach ((array)$segments as $segment) {
            $id = $segment['service_variation_id'] ?? $segment['serviceVariationId'] ?? null;

            if ($id !== null) {
                $treatments[] = $names[$id] ?? 'Treatment';
            }
        }

        return $treatments === [] ? ['Appointment'] : $treatments;
    }
}
