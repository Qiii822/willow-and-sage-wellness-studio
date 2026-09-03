<?php
declare(strict_types=1);

namespace App\Service\Sync;

use App\Model\Entity\Import;
use App\Service\SquareApiService;
use App\Service\SquareAuthService;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use RuntimeException;
use Throwable;

/**
 * Syncs the local `services` treatment menu against Square's catalogue.
 *
 * Like the customer, booking and payment syncs, every run is a full
 * reimport: the local table is emptied and reloaded from Square, so after a
 * completed run it matches Square exactly. A treatment typed in by hand,
 * or edited in the dashboard, is replaced by whatever Square says.
 *
 * Two columns survive that replacement, keyed by the Square id: `visible`
 * and `position`. Square has no way to express "keep this off the website"
 * or "show this one first" - those are decisions about the site, not about
 * the catalogue. Resetting them every run would unhide a treatment the
 * studio had deliberately taken down, and reshuffle an order someone had
 * arranged, with no way to say otherwise in Square.
 */
class ServiceSyncService
{
    /**
     * Where a treatment goes when Square has not filed it under a category.
     * A blank category would fail validation and, worse, would leave the
     * public page with an unnamed group.
     */
    protected const UNFILED_CATEGORY = 'Other treatments';

    protected SquareAuthService $squareAuth;
    protected SquareApiService $squareApi;
    protected Table $servicesTable;
    protected Table $categoriesTable;
    protected Table $importsTable;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->squareAuth = new SquareAuthService();
        $this->squareApi = new SquareApiService();
        $this->servicesTable = TableRegistry::getTableLocator()->get('Services');
        $this->categoriesTable = TableRegistry::getTableLocator()->get('ServiceCategories');
        $this->importsTable = TableRegistry::getTableLocator()->get('Imports');
    }

    /**
     * Runs a full treatment-menu sync against Square.
     *
     * @param string $source One of: oauth_callback, cron, manual, webhook.
     * @return \App\Model\Entity\Import The completed (or failed) Import record.
     * @throws \RuntimeException If Square isn't connected, or a sync is already running.
     */
    public function run(string $source): Import
    {
        $identity = $this->squareAuth->getActiveIdentity();

        if (!$identity) {
            throw new RuntimeException('Cannot sync - Square is not connected.');
        }

        if ($this->importsTable->hasRunningImport('services')) {
            throw new RuntimeException('A service sync is already running - try again shortly.');
        }

        $import = $this->importsTable->newEntity([
            'identity_id' => $identity->id,
            'resource' => 'services',
            'source' => $source,
            'status' => 'running',
            'started_at' => DateTime::now(),
        ]);
        $this->importsTable->saveOrFail($import);

        try {
            /*
             * Everything is pulled before anything is deleted, so a failure
             * on Square's side cannot leave the menu emptied with nothing to
             * put back.
             */
            $services = $this->squareApi->listServiceItems();
            $squareCategories = $this->squareApi->listServiceCategoryNames();

            $presentation = $this->currentPresentation();

            $this->servicesTable->getConnection()->transactional(
                function () use ($services, $presentation): void {
                    $this->servicesTable->deleteAll([]);

                    $position = 0;

                    foreach ($services as $service) {
                        $id = $service['square_catalog_id'];

                        $entity = $this->servicesTable->newEntity([
                            'category' => $service['category'] ?? self::UNFILED_CATEGORY,
                            'name' => $service['name'],
                            'description' => $service['description'],
                            'price_cents' => $service['price_cents'],
                            'currency' => 'AUD',
                            'duration_minutes' => $service['duration_minutes'],
                        ]);

                        /*
                         * Both are off-limits to a submitted form, so they
                         * are set here rather than passed through newEntity.
                         */
                        $entity->square_catalog_id = $id;
                        $entity->position = $presentation[$id]['position'] ?? $position;
                        $entity->visible = $presentation[$id]['visible'] ?? true;

                        $this->servicesTable->saveOrFail($entity);

                        $position++;
                    }
                },
            );

            /*
             * The category list is replaced, not added to, so it ends up
             * matching Square exactly like everything else here. One typed
             * in locally and not backed by Square goes with the rest.
             *
             * Categories actually in use are kept alongside Square's own,
             * even when Square defines none: a treatment with no category
             * in Square falls back to UNFILED_CATEGORY, and dropping that
             * from the list would leave the public page grouping under a
             * heading the management screen does not admit exists.
             */
            $this->categoriesTable->replaceWith(array_merge(
                $squareCategories,
                array_map(
                    fn(array $service): string => $service['category'] ?? self::UNFILED_CATEGORY,
                    $services,
                ),
            ));

            $import = $this->importsTable->patchEntity($import, [
                'status' => 'completed',
                'finished_at' => DateTime::now(),
            ]);
            $this->importsTable->saveOrFail($import);
        } catch (Throwable $e) {
            Log::error('Service sync failed: ' . $e->getMessage());

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
     * The show/hide and ordering choices currently in force, keyed by the
     * Square id they belong to.
     *
     * Rows with no Square id are hand-entered ones about to be removed, and
     * have nothing for a synced treatment to inherit.
     *
     * @return array<string, array{visible: bool, position: int}>
     */
    protected function currentPresentation(): array
    {
        $rows = $this->servicesTable->find()
            ->select(['square_catalog_id', 'visible', 'position'])
            ->where(['square_catalog_id IS NOT' => null])
            ->disableHydration()
            ->all();

        $presentation = [];

        foreach ($rows as $row) {
            $presentation[(string)$row['square_catalog_id']] = [
                'visible' => (bool)$row['visible'],
                'position' => (int)$row['position'],
            ];
        }

        return $presentation;
    }
}
