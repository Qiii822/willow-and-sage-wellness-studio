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
 * After a completed run the menu matches Square exactly: a treatment typed
 * in by hand, or edited in the dashboard, is replaced by whatever Square
 * says, and one Square does not have is removed.
 *
 * It gets there by matching on the Square id rather than by emptying the
 * table and reloading it. That matters because a row's id is not only its
 * own: an offer points at the treatment it is about, and a booking draft
 * records the treatments it was started for. Deleting and recreating gave
 * every treatment a new id on every sync, which quietly broke those links -
 * an offer would stop finding its treatment the first time anyone pressed
 * Sync. Keeping the row keeps the id, and anything pointing at it.
 *
 * Two columns are left alone entirely: `visible` and `position`. Square has
 * no way to express "keep this off the website" or "show this one first" -
 * those are decisions about the site, not about the catalogue. Resetting
 * them every run would unhide a treatment the studio had deliberately taken
 * down, and reshuffle an order someone had arranged, with no way to say
 * otherwise in Square.
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

            $this->servicesTable->getConnection()->transactional(
                function () use ($services): void {
                    $existing = $this->existingBySquareId();
                    $position = 0;

                    foreach ($services as $service) {
                        $id = $service['square_catalog_id'];
                        $entity = $existing[$id] ?? null;

                        if ($entity === null) {
                            $entity = $this->servicesTable->newEmptyEntity();
                            $entity->square_catalog_id = $id;

                            /*
                             * Only a treatment that has never been here
                             * takes its place from Square's order and
                             * arrives shown. One already on the menu keeps
                             * wherever the studio put it.
                             */
                            $entity->position = $position;
                            $entity->visible = true;
                        }

                        $entity->category = $service['category'] ?? self::UNFILED_CATEGORY;
                        $entity->name = $service['name'];
                        $entity->description = $service['description'];
                        $entity->price_cents = $service['price_cents'];
                        $entity->currency = 'AUD';
                        $entity->duration_minutes = $service['duration_minutes'];

                        // Off-limits to a submitted form, so set directly.
                        $entity->square_variation_id = $service['square_variation_id'] ?? null;

                        $this->servicesTable->saveOrFail($entity);

                        unset($existing[$id]);
                        $position++;
                    }

                    /*
                     * What is left was on the menu and is not in Square's
                     * catalogue any more, so it has been deleted there.
                     */
                    foreach ($existing as $gone) {
                        $this->servicesTable->delete($gone);
                    }

                    /*
                     * And anything that never came from Square - typed in by
                     * hand before the menu was Square's. The dashboard has
                     * no way to add or remove one, so nothing else would.
                     */
                    $this->servicesTable->deleteAll(['square_catalog_id IS' => null]);
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
     * The treatments already on the menu, keyed by their Square id.
     *
     * These are the rows a sync updates in place, which is what keeps a
     * treatment's id - and so keeps the offers and booking drafts pointing
     * at it - across a run.
     *
     * Rows with no Square id are hand-entered ones about to be removed, and
     * there is nothing in Square for them to be matched against.
     *
     * @return array<string, \App\Model\Entity\Service>
     */
    protected function existingBySquareId(): array
    {
        $rows = $this->servicesTable->find()
            ->where(['square_catalog_id IS NOT' => null])
            ->all();

        $bySquareId = [];

        foreach ($rows as $row) {
            $bySquareId[(string)$row->square_catalog_id] = $row;
        }

        return $bySquareId;
    }
}
