<?php
declare(strict_types=1);

namespace App\Service\Sync;

use App\Service\SquareApiService;
use App\Service\SquareAuthService;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Square\Types\Customer as SquareCustomer;

/**
 * Syncs local `customers` data against Square, via a full pull of every
 * customer on each run.
 *
 * Each run creates one Import row, and one CustomerChange row per
 * customer that was actually modified. Fields compared against 
 * Square's version counter
 */
class CustomerSyncService
{
    // Only these fields are compared and logged
    // version/timestamp columns are ignored
    protected const TRACKED_FIELDS = ['given_name', 'family_name', 'email_address', 'phone_number'];

    protected SquareAuthService $squareAuth;
    protected SquareApiService $squareApi;
    protected \Cake\ORM\Table $identitiesTable;
    protected \Cake\ORM\Table $customersTable;
    protected \Cake\ORM\Table $importsTable;
    protected \Cake\ORM\Table $customerChangesTable;

    public function __construct()
    {
        $this->squareAuth = new SquareAuthService();
        $this->squareApi = new SquareApiService();
        $this->identitiesTable = TableRegistry::getTableLocator()->get('Identities');
        $this->customersTable = TableRegistry::getTableLocator()->get('Customers');
        $this->importsTable = TableRegistry::getTableLocator()->get('Imports');
        $this->customerChangesTable = TableRegistry::getTableLocator()->get('CustomerChanges');
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

        // Stops two syncs writing to the customers table at once.
        if ($this->importsTable->hasRunningImport()) {
            throw new \RuntimeException('A sync is already running - try again shortly.');
        }

        // Get the Import row to log each change to this import ID
        $import = $this->importsTable->newEntity([
            'identity_id' => $identity->id,
            'source' => $source,
            'status' => 'running',
            'started_at' => DateTime::now(),
        ]);
        $this->importsTable->saveOrFail($import);

        try {
            // Pull every customer from Square (auto-paginated) and upsert each one locally.
            $seenSquareIds = [];

            foreach ($this->squareApi->listCustomers() as $squareCustomer) {
                /** @var \Square\Types\Customer $squareCustomer */
                $seenSquareIds[] = $squareCustomer->getId();
                $this->upsertCustomer($import, $squareCustomer);
            }

            // Square doesn't report deletions directly - anything local
            // that wasn't in this pull is presumed deleted
            $this->markMissingAsDeleted($import, $seenSquareIds);

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

        return $import;
    }

    /**
     * Creates or updates the local Customer row for a single Square
     * customer, logging a CustomerChange if anything actually changed.
     *
     * @param \App\Model\Entity\Import $import
     * @param \Square\Types\Customer $squareCustomer
     * @return void
     */
    protected function upsertCustomer(\App\Model\Entity\Import $import, SquareCustomer $squareCustomer): void
    {
        // Look up the local copy (if any) and Square's current version number for this customer
        $existing = $this->customersTable->findBySquareCustomerId((string)$squareCustomer->getId());
        $newVersion = $squareCustomer->getVersion() ?? 0;

        if ($existing && $existing->square_version >= $newVersion) {
            // Already up to date nothing to do.
            return;
        }

        $data = [
            'square_customer_id' => (string)$squareCustomer->getId(),
            'given_name' => $squareCustomer->getGivenName(),
            'family_name' => $squareCustomer->getFamilyName(),
            'email_address' => $squareCustomer->getEmailAddress(),
            'phone_number' => $squareCustomer->getPhoneNumber(),
            'square_version' => $newVersion,
            'square_updated_at' => $squareCustomer->getUpdatedAt()
                ? DateTime::parse($squareCustomer->getUpdatedAt())
                : null,
            'square_created_at' => $squareCustomer->getCreatedAt()
                ? DateTime::parse($squareCustomer->getCreatedAt())
                : null,
            'deleted_at' => null, // un-delete, if this customer had previously disappeared
        ];

        // Existing row = update (with a diff to log); no row = create (nothing to diff against).
        $action = $existing ? 'update' : 'create';
        $diff = $existing ? $this->buildDiff($existing, $data) : null;

        // Version changed but nothing we track actually differs - update silently, skip the log.
        if ($action === 'update' && empty($diff)) {
            $entity = $this->customersTable->patchEntity($existing, [
                'square_version' => $newVersion,
                'updated_at' => DateTime::now(),
            ]);
            $this->customersTable->saveOrFail($entity);

            return;
        }

        // Upsert: reuse the existing row if we found one, otherwise start a new one, then save.
        $entity = $existing ?? $this->customersTable->newEmptyEntity();
        $entity = $this->customersTable->patchEntity($entity, $data);
        $this->customersTable->saveOrFail($entity);

        $this->logChange($import, $data['square_customer_id'], $action, $diff);
    }

    /**
     * Soft-deletes any local Customer not present in the current full
     * pull's results, and logs the deletion.
     *
     * @param \App\Model\Entity\Import $import
     * @param string[] $seenSquareIds Every square_customer_id returned by this pull.
     * @return void
     */
    protected function markMissingAsDeleted(\App\Model\Entity\Import $import, array $seenSquareIds): void
    {
        $query = $this->customersTable->find()->where(['deleted_at IS' => null]);

        // Guard the empty case - "NOT IN ()" is invalid SQL, and an empty
        // pull means everything local is correctly considered missing.
        if ($seenSquareIds !== []) {
            $query = $query->andWhere(['square_customer_id NOT IN' => $seenSquareIds]);
        }

        foreach ($query as $customer) {
            $entity = $this->customersTable->patchEntity($customer, [
                'deleted_at' => DateTime::now(),
                'updated_at' => DateTime::now(),
            ]);
            $this->customersTable->saveOrFail($entity);

            $this->logChange($import, $customer->square_customer_id, 'delete', null);
        }
    }

    /**
     * Builds a field-name => [old, new] diff between an existing local
     * Customer and the incoming data, limited to TRACKED_FIELDS.
     *
     * @param \App\Model\Entity\Customer $existing
     * @param array $newData
     * @return array
     */
    protected function buildDiff(\App\Model\Entity\Customer $existing, array $newData): array
    {
        $diff = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $old = $existing->{$field};
            $new = $newData[$field];

            if ($old !== $new) {
                $diff[$field] = [$old, $new];
            }
        }

        return $diff;
    }

    /**
     * Records a CustomerChange row for this import.
     *
     * @param \App\Model\Entity\Import $import
     * @param string $squareCustomerId
     * @param string $action create, update, or delete.
     * @param array|null $diff
     * @return void
     */
    protected function logChange(
        \App\Model\Entity\Import $import,
        string $squareCustomerId,
        string $action,
        ?array $diff
    ): void {
        $change = $this->customerChangesTable->newEntity([
            'import_id' => $import->id,
            'square_customer_id' => $squareCustomerId, // stored directly, not as a local FK, so this survives hard-deletes
            'action' => $action,
            'diff' => $diff ? json_encode($diff) : null,
            'created_at' => DateTime::now(),
        ]);

        $this->customerChangesTable->saveOrFail($change);
    }
}