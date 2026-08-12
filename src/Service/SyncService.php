<?php
declare(strict_types=1);

namespace App\Service;

use App\Service\Sync\CustomerSyncService;

/**
 * Single entry point for triggering a sync against Square.
 *
 * Callers (AuthController, the cron command, webhook handlers) call this
 * rather than individual resource sync services directly. That way,
 * adding a new synced resource later (e.g. bookings) means adding one
 * method/line here, rather than editing every call site that currently
 * triggers a customer sync.
 */
class SyncService
{
    /**
     * Runs every resource sync against Square.
     *
     * @param string $source One of: oauth_callback, cron, manual, webhook.
     * @return void
     */
    public function runAll(string $source): void
    {
        $this->syncCustomers($source);

        // Add future resources here, e.g.:
        // $this->syncBookings($source);
    }

    /**
     * Runs only the customer sync.
     *
     * @param string $source One of: oauth_callback, cron, manual, webhook.
     * @return \App\Model\Entity\Import
     */
    public function syncCustomers(string $source): \App\Model\Entity\Import
    {
        return (new CustomerSyncService())->run($source);
    }
}
