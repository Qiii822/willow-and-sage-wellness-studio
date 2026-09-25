<?php
declare(strict_types=1);

namespace App\Service\Sync;

use App\Service\SquareApiService;
use App\Service\SquareAuthService;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Square\Types\Customer as SquareCustomer;

/**
 * Syncs local `customers` data against Square.
 *
 * Every run is a full reimport: all local customers are deleted and
 * replaced with a fresh pull from Square. This trades the per-customer
 * change history for a simpler guarantee - the local table always
 * matches Square exactly after a completed run, with no diffing,
 * versioning, or soft-delete bookkeeping to keep in sync.
 */
class CustomerSyncService
{
    protected SquareAuthService $squareAuth;
    protected SquareApiService $squareApi;
    protected \Cake\ORM\Table $identitiesTable;
    protected \Cake\ORM\Table $customersTable;
    protected \Cake\ORM\Table $importsTable;

    public function __construct()
    {
        $this->squareAuth = new SquareAuthService();
        $this->squareApi = new SquareApiService();
        $this->identitiesTable = TableRegistry::getTableLocator()->get('Identities');
        $this->customersTable = TableRegistry::getTableLocator()->get('Customers');
        $this->importsTable = TableRegistry::getTableLocator()->get('Imports');
    }

    /**
     * Runs a full customer sync against Square.
     *
     * @param string $source One of: oauth_callback, cron, manual, webhook.
     * @return \App\Model\Entity\Import The completed (or failed) Import record.
     * @throws \RuntimeException If Square isn't connected, or an import is already running.
     */
    public function run(string $source): \App\Model\Entity\Import
    {
        $identity = $this->squareAuth->getActiveIdentity();

        if (!$identity) {
            throw new \RuntimeException('Cannot sync - Square is not connected.');
        }

        // Stops two customer syncs writing to the customers table at once.
        if ($this->importsTable->hasRunningImport('customers')) {
            throw new \RuntimeException('A customer sync is already running - try again shortly.');
        }

        // One Import row per run, marking start/end and outcome of this reimport
        $import = $this->importsTable->newEntity([
            'identity_id' => $identity->id,
            'resource' => 'customers',
            'source' => $source,
            'status' => 'running',
            'started_at' => DateTime::now(),
        ]);
        $this->importsTable->saveOrFail($import);

        try {
            // Pull every customer from Square first (auto-paginated), so a Square-side
            // failure can't leave the local table wiped with nothing to replace it.
            $customers = [];
            foreach ($this->squareApi->listCustomers() as $squareCustomer) {
                /** @var \Square\Types\Customer $squareCustomer */
                $customers[] = $this->mapCustomer($squareCustomer);
            }

            // Wipe and reload atomically - the local table always ends up either
            // fully replaced or, on failure, untouched.
            $this->customersTable->getConnection()->transactional(function () use ($customers): void {
                $this->customersTable->deleteAll([]);

                foreach ($customers as $data) {
                    $entity = $this->customersTable->newEntity($data);
                    $this->customersTable->saveOrFail($entity);
                }
            });

            $import = $this->importsTable->patchEntity($import, [
                'status' => 'completed',
                'finished_at' => DateTime::now(),
            ]);
            $this->importsTable->saveOrFail($import);
        } catch (\Throwable $e) {
            // Mark the import failed rather than leaving it stuck on "running", then re-throw.
            \Cake\Log\Log::error('Customer sync failed: ' . $e->getMessage());

            $import = $this->importsTable->patchEntity($import, [
                'status' => 'failed',
                'finished_at' => DateTime::now(),
                'error_message' => $e->getMessage(),
            ]);
            $this->importsTable->save($import);

            throw $e;
        }

        /*
         * Customers that did not exist locally a moment ago have just
         * arrived, so accounts waiting on them can now be matched.
         *
         * Deliberately outside the try above: linking is bookkeeping on top
         * of a sync that has already succeeded, and a failure here must not
         * mark a good import as failed.
         */
        try {
            $linked = (new CustomerLinkService())->linkUnlinkedUsers();

            if ($linked > 0) {
                \Cake\Log\Log::info(
                    'Linked ' . $linked . ' account(s) to Square customers after import ' . $import->id
                );
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::error('Customer linking failed after sync: ' . $e->getMessage());
        }

        return $import;
    }

    /**
     * Maps a Square customer to the local `customers` row shape.
     *
     * @param \Square\Types\Customer $squareCustomer
     * @return array
     */
    protected function mapCustomer(SquareCustomer $squareCustomer): array
    {
        return [
            'square_customer_id' => (string)$squareCustomer->getId(),
            'given_name' => $squareCustomer->getGivenName(),
            'family_name' => $squareCustomer->getFamilyName(),
            'email_address' => $squareCustomer->getEmailAddress(),
            'phone_number' => $squareCustomer->getPhoneNumber(),
            'square_version' => $squareCustomer->getVersion() ?? 0,
            'square_updated_at' => $squareCustomer->getUpdatedAt()
                ? DateTime::parse($squareCustomer->getUpdatedAt())
                : null,
            'square_created_at' => $squareCustomer->getCreatedAt()
                ? DateTime::parse($squareCustomer->getCreatedAt())
                : null,
        ];
    }
}