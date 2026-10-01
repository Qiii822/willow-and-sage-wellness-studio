<?php
declare(strict_types=1);

namespace App\Service\Sync;

use App\Service\SquareApiService;
use App\Service\SquareAuthService;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Square\Types\Payment as SquarePayment;

/**
 * Syncs local `payments` data against Square.
 *
 * Like CustomerSyncService and BookingSyncService, every run is a full
 * reimport: all local payments are deleted and replaced with a fresh pull
 * from Square. No diffing or versioning - the local table always matches
 * Square exactly after a completed run.
 */
class PaymentSyncService
{
    /**
     * Square defaults ListPayments' begin_time to one year ago when it's
     * omitted, which would silently truncate a "full" reimport. This is
     * used as an explicit lower bound instead, well before Square existed.
     */
    protected const EARLIEST_BEGIN_TIME = '2010-01-01T00:00:00Z';

    protected SquareAuthService $squareAuth;
    protected SquareApiService $squareApi;
    protected \Cake\ORM\Table $paymentsTable;
    protected \Cake\ORM\Table $importsTable;

    public function __construct()
    {
        $this->squareAuth = new SquareAuthService();
        $this->squareApi = new SquareApiService();
        $this->paymentsTable = TableRegistry::getTableLocator()->get('Payments');
        $this->importsTable = TableRegistry::getTableLocator()->get('Imports');
    }

    /**
     * Runs a full payment sync against Square.
     *
     * @param string $source One of: oauth_callback, cron, manual, webhook.
     * @return \App\Model\Entity\Import The completed (or failed) Import record.
     * @throws \RuntimeException If Square isn't connected, or a payment sync is already running.
     */
    public function run(string $source): \App\Model\Entity\Import
    {
        $identity = $this->squareAuth->getActiveIdentity();

        if (!$identity) {
            throw new \RuntimeException('Cannot sync - Square is not connected.');
        }

        // Stops two payment syncs writing to the payments table at once.
        if ($this->importsTable->hasRunningImport('payments')) {
            throw new \RuntimeException('A payment sync is already running - try again shortly.');
        }

        // One Import row per run, marking start/end and outcome of this reimport
        $import = $this->importsTable->newEntity([
            'identity_id' => $identity->id,
            'resource' => 'payments',
            'source' => $source,
            'status' => 'running',
            'started_at' => DateTime::now(),
        ]);
        $this->importsTable->saveOrFail($import);

        try {
            // Pull every payment from Square first (auto-paginated), so a Square-side
            // failure can't leave the local table wiped with nothing to replace it.
            $payments = [];
            foreach ($this->squareApi->listPayments(beginTime: self::EARLIEST_BEGIN_TIME) as $squarePayment) {
                /** @var \Square\Types\Payment $squarePayment */
                $payments[] = $this->mapPayment($squarePayment);
            }

            // Wipe and reload atomically - the local table always ends up either
            // fully replaced or, on failure, untouched.
            $this->paymentsTable->getConnection()->transactional(function () use ($payments): void {
                $this->paymentsTable->deleteAll([]);

                foreach ($payments as $data) {
                    $entity = $this->paymentsTable->newEntity($data);
                    $this->paymentsTable->saveOrFail($entity);
                }
            });

            $import = $this->importsTable->patchEntity($import, [
                'status' => 'completed',
                'finished_at' => DateTime::now(),
            ]);
            $this->importsTable->saveOrFail($import);
        } catch (\Throwable $e) {
            // Mark the import failed rather than leaving it stuck on "running", then re-throw.
            \Cake\Log\Log::error('Payment sync failed: ' . $e->getMessage());

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
     * Maps a Square payment to the local `payments` row shape.
     *
     * Public so a payment recorded from a booking can be written to the
     * local table straight away, in the same shape the next sync will give
     * it, rather than missing from the payments page until then.
     *
     * @param \Square\Types\Payment $squarePayment
     * @return array
     */
    public function mapPayment(SquarePayment $squarePayment): array
    {
        $amountMoney = $squarePayment->getAmountMoney();
        $totalMoney = $squarePayment->getTotalMoney();

        return [
            'square_payment_id' => (string)$squarePayment->getId(),
            // Square's status is e.g. "COMPLETED" - lowercased to match
            // this app's lowercase-enum convention (e.g. bookings.status).
            'status' => strtolower((string)$squarePayment->getStatus()),
            // Currency is always AUD for this account - not stored.
            'amount' => $amountMoney?->getAmount() ?? 0,
            'total_amount' => $totalMoney?->getAmount(),
            'source_type' => $squarePayment->getSourceType(),
            'square_customer_id' => $squarePayment->getCustomerId(),
            'order_id' => $squarePayment->getOrderId(),
            'reference_id' => $squarePayment->getReferenceId(),
            'receipt_number' => $squarePayment->getReceiptNumber(),
            'receipt_url' => $squarePayment->getReceiptUrl(),
            'note' => $squarePayment->getNote(),
            'square_created_at' => $squarePayment->getCreatedAt()
                ? DateTime::parse($squarePayment->getCreatedAt())
                : null,
            'square_updated_at' => $squarePayment->getUpdatedAt()
                ? DateTime::parse($squarePayment->getUpdatedAt())
                : null,
        ];
    }
}
