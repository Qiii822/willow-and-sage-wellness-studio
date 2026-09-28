<?php
declare(strict_types=1);

namespace App\Service\Sync;

use App\Service\SquareApiService;
use App\Service\SquareAuthService;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use DateTimeImmutable;
use Square\Types\Booking as SquareBooking;

/**
 * Syncs local `bookings` data against Square.
 *
 * Like CustomerSyncService, every run is a full reimport: all local
 * bookings are deleted and replaced with a fresh pull from Square. No
 * diffing, versioning, or soft-delete bookkeeping - the local table
 * always matches Square exactly after a completed run.
 */
class BookingSyncService
{
    /**
     * How far either side of today the sync reaches.
     *
     * Because every run replaces the table wholesale, this is not just what
     * gets fetched - it is the whole of what the dashboard can ever show.
     * Far enough back to look up what happened last season, and out to
     * Square's own one-year booking horizon.
     */
    protected const MONTHS_OF_HISTORY = 6;

    protected const MONTHS_AHEAD = 12;

    protected SquareAuthService $squareAuth;
    protected SquareApiService $squareApi;
    protected \Cake\ORM\Table $bookingsTable;
    protected \Cake\ORM\Table $importsTable;

    public function __construct()
    {
        $this->squareAuth = new SquareAuthService();
        $this->squareApi = new SquareApiService();
        $this->bookingsTable = TableRegistry::getTableLocator()->get('Bookings');
        $this->importsTable = TableRegistry::getTableLocator()->get('Imports');
    }

    /**
     * Runs a full booking sync against Square.
     *
     * @param string $source One of: oauth_callback, cron, manual, webhook.
     * @return \App\Model\Entity\Import The completed (or failed) Import record.
     * @throws \RuntimeException If Square isn't connected, or a booking sync is already running.
     */
    public function run(string $source): \App\Model\Entity\Import
    {
        $identity = $this->squareAuth->getActiveIdentity();

        if (!$identity) {
            throw new \RuntimeException('Cannot sync - Square is not connected.');
        }

        // Stops two booking syncs writing to the bookings table at once.
        if ($this->importsTable->hasRunningImport('bookings')) {
            throw new \RuntimeException('A booking sync is already running - try again shortly.');
        }

        // One Import row per run, marking start/end and outcome of this reimport
        $import = $this->importsTable->newEntity([
            'identity_id' => $identity->id,
            'resource' => 'bookings',
            'source' => $source,
            'status' => 'running',
            'started_at' => DateTime::now(),
        ]);
        $this->importsTable->saveOrFail($import);

        try {
            /*
             * An explicit range, not Square's default. Asked for no range,
             * ListBookings answers from now onwards - so every appointment
             * already past is missing, and the dashboard shows an empty
             * Monday for a day that was fully booked.
             *
             * This window is also the whole of what the dashboard can ever
             * show, because the reload below replaces the table with it.
             */
            $now = new DateTimeImmutable('now');

            $bookings = [];

            foreach (
                $this->squareApi->listBookingsBetween(
                    $now->modify('-' . self::MONTHS_OF_HISTORY . ' months'),
                    $now->modify('+' . self::MONTHS_AHEAD . ' months'),
                ) as $squareBooking
            ) {
                /** @var \Square\Types\Booking $squareBooking */
                $bookings[] = $this->mapBooking($squareBooking);
            }

            // Guard for Zero Bookings
            if ($bookings === [] && $this->bookingsTable->find()->count() > 0) {
                throw new \RuntimeException('Square returned zero bookings; refusing to wipe local table.');
            }

            // Wipe and reload atomically - the local table always ends up either
            // fully replaced or, on failure, untouched.
            $this->bookingsTable->getConnection()->transactional(function () use ($bookings): void {
                $this->bookingsTable->deleteAll([]);

                foreach ($bookings as $data) {
                    $entity = $this->bookingsTable->newEntity($data);
                    $this->bookingsTable->saveOrFail($entity);
                }
            });

            $import = $this->importsTable->patchEntity($import, [
                'status' => 'completed',
                'finished_at' => DateTime::now(),
            ]);
            $this->importsTable->saveOrFail($import);
        } catch (\Throwable $e) {
            // Mark the import failed rather than leaving it stuck on "running", then re-throw.
            \Cake\Log\Log::error('Booking sync failed: ' . $e->getMessage());

            $import = $this->importsTable->patchEntity($import, [
                'status' => 'failed',
                'finished_at' => DateTime::now(),
                'error_message' => $e->getMessage(),
            ]);
            $this->importsTable->save($import);

            throw $e;
        }

        return $import;
    }

    /**
     * Maps a Square booking to the local `bookings` row shape.
     *
     * @param \Square\Types\Booking $squareBooking
     * @return array
     */
    protected function mapBooking(SquareBooking $squareBooking): array
    {
        $segments = array_map(
            fn ($segment) => [
                'duration_minutes' => $segment->getDurationMinutes(),
                'service_variation_id' => $segment->getServiceVariationId(),
                'service_variation_version' => $segment->getServiceVariationVersion(),
                'team_member_id' => $segment->getTeamMemberId(),
            ],
            $squareBooking->getAppointmentSegments() ?? []
        );

        return [
            'square_booking_id' => (string)$squareBooking->getId(),
            // Square's status is e.g. "CANCELLED_BY_CUSTOMER" - lowercased to
            // match this app's lowercase-enum convention (e.g. `source`/
            // `status` on imports).
            'status' => strtolower((string)$squareBooking->getStatus()),
            'location_id' => $squareBooking->getLocationId(),
            'square_customer_id' => $squareBooking->getCustomerId(),
            'start_at' => $squareBooking->getStartAt()
                ? DateTime::parse($squareBooking->getStartAt())
                : null,
            'customer_note' => $squareBooking->getCustomerNote(),
            'seller_note' => $squareBooking->getSellerNote(),
            'appointment_segments' => $segments ? json_encode($segments) : null,
            'square_version' => $squareBooking->getVersion() ?? 0,
            'square_updated_at' => $squareBooking->getUpdatedAt()
                ? DateTime::parse($squareBooking->getUpdatedAt())
                : null,
            'square_created_at' => $squareBooking->getCreatedAt()
                ? DateTime::parse($squareBooking->getCreatedAt())
                : null,
        ];
    }
}
