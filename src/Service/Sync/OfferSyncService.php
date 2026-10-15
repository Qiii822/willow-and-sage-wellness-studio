<?php
declare(strict_types=1);

namespace App\Service\Sync;

use App\Model\Entity\Import;
use App\Model\Entity\Offer;
use App\Service\ImageUploadService;
use App\Service\SquareApiService;
use App\Service\SquareAuthService;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use RuntimeException;
use Throwable;

/**
 * Syncs the local `offers` list against the discounts in Square's catalogue.
 *
 * This one deliberately does not work like the treatment menu sync, which
 * empties its table and reloads it from Square every run. That is right for
 * treatments, where Square holds every field worth having. It would be
 * destructive here: a Square discount is only a name and an amount, while an
 * offer card is mostly description, small print and a picture, none of which
 * Square has anywhere to keep. A full reimport would wipe the studio's
 * writing every time anyone pressed Sync.
 *
 * So offers are matched up by the Square id instead:
 *
 * - a discount with no offer yet gets one, hidden;
 * - a discount that already has one updates its name and its percentage,
 *   and leaves everything else exactly as the studio left it;
 * - an offer whose discount has been deleted in Square goes too.
 *
 * An offer with no Square id goes as well. The dashboard has no Add any
 * more, so one can only be left over from before offers came from Square -
 * and it is unreachable: nothing in Square will ever update it, and the
 * screens that could have removed it are gone.
 *
 * New offers arrive hidden on purpose. Not every discount in a Square
 * account is something to advertise - a staff discount, or one kept for
 * regulars - and a sync that put all of them straight onto the home page
 * would publish the studio's internal pricing the first time it ran.
 */
class OfferSyncService
{
    protected SquareAuthService $squareAuth;
    protected SquareApiService $squareApi;
    protected Table $offersTable;
    protected Table $importsTable;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->squareAuth = new SquareAuthService();
        $this->squareApi = new SquareApiService();
        $this->offersTable = TableRegistry::getTableLocator()->get('Offers');
        $this->importsTable = TableRegistry::getTableLocator()->get('Imports');
    }

    /**
     * Runs a full offers sync against Square.
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

        if ($this->importsTable->hasRunningImport('offers')) {
            throw new RuntimeException('An offers sync is already running - try again shortly.');
        }

        $import = $this->importsTable->newEntity([
            'identity_id' => $identity->id,
            'resource' => 'offers',
            'source' => $source,
            'status' => 'running',
            'started_at' => DateTime::now(),
        ]);
        $this->importsTable->saveOrFail($import);

        try {
            /*
             * Pulled before anything is written, so a failure on Square's
             * side cannot leave the list half-updated - and, in particular,
             * cannot delete offers on the strength of an empty reply.
             */
            $discounts = $this->squareApi->listDiscounts();

            $this->offersTable->getConnection()->transactional(
                function () use ($discounts): void {
                    $existing = $this->existingBySquareId();
                    $position = $this->offersTable->nextPosition();

                    foreach ($discounts as $discount) {
                        $id = $discount['square_catalog_id'];
                        $offer = $existing[$id] ?? null;

                        if ($offer === null) {
                            $offer = $this->offersTable->newEmptyEntity();
                            $offer->square_catalog_id = $id;
                            $offer->position = $position++;

                            // Hidden until the studio says otherwise - see
                            // the note at the top of this class.
                            $offer->visible = false;

                            /*
                             * A first guess at what the discount is for, and
                             * only ever on a new offer. Studios name a
                             * discount after the treatment it is for, so an
                             * exact match is usually right - and when it is
                             * not, the studio changes it on the offer and no
                             * later sync overrides them.
                             */
                            $offer->service_id = $this->serviceNamed($discount['name']);
                        }

                        /*
                         * The only two fields Square owns. Everything else on
                         * the row - description, small print, picture, the
                         * before and after prices, whether it is shown and
                         * where it sits - is the studio's and is left alone.
                         */
                        $offer->title = $discount['name'];
                        $offer->discount_percent = $discount['discount_percent'];
                        $offer->discount_amount_cents = $discount['discount_amount_cents'];

                        /*
                         * A treatment that is no longer there. The studio
                         * cannot have meant this - it is a link to something
                         * that has been deleted in Square - so it is not
                         * treated as their choice: it is cleared, and guessed
                         * again in case the treatment came back under the
                         * same name. A deliberate "no single treatment" is
                         * null to begin with and never reaches here.
                         */
                        if ($offer->service_id !== null && !$this->serviceExists($offer->service_id)) {
                            $offer->service_id = $this->serviceNamed($discount['name']);
                        }

                        $this->offersTable->saveOrFail($offer);

                        unset($existing[$id]);
                    }

                    /*
                     * What is left came from Square once and is not in the
                     * catalogue any more, so the discount has been deleted
                     * there.
                     */
                    foreach ($existing as $offer) {
                        $this->remove($offer);
                    }

                    /*
                     * And anything that never had a Square id, which can only
                     * be an offer typed in before the dashboard's Add was
                     * taken away. Left alone it would sit on the home page
                     * for good, with nothing able to change or remove it.
                     */
                    foreach ($this->offersTable->find()->where(['square_catalog_id IS' => null])->all() as $orphan) {
                        $this->remove($orphan);
                    }
                },
            );

            $import = $this->importsTable->patchEntity($import, [
                'status' => 'completed',
                'finished_at' => DateTime::now(),
            ]);
            $this->importsTable->saveOrFail($import);
        } catch (Throwable $e) {
            Log::error('Offer sync failed: ' . $e->getMessage());

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
     * Whether the treatment an offer points at is still on the menu.
     *
     * @param string $serviceId
     * @return bool
     */
    protected function serviceExists(string $serviceId): bool
    {
        return TableRegistry::getTableLocator()->get('Services')
            ->exists(['id' => $serviceId]);
    }

    /**
     * The id of the treatment going by this name, if exactly one does.
     *
     * Deliberately strict. A loose match - "contains", or nearest by
     * similarity - would quietly point "10% off facials" at whichever
     * facial happened to sort first, and send customers into booking the
     * wrong treatment. Nothing is worse here than a wrong guess, so an
     * uncertain one is no guess at all and the studio picks.
     *
     * @param string $name
     * @return string|null
     */
    protected function serviceNamed(string $name): ?string
    {
        $matches = TableRegistry::getTableLocator()->get('Services')
            ->find()
            ->select(['id'])
            ->where(['name' => trim($name)])
            ->limit(2)
            ->all()
            ->toList();

        return count($matches) === 1 ? (string)$matches[0]->id : null;
    }

    /**
     * Removes one offer and the picture that belonged to it.
     *
     * The dashboard's delete button used to do this. With that gone, the
     * sync is the only thing that removes an offer, so it has to be the one
     * that tidies up after it - otherwise every discount deleted in Square
     * leaves its photo behind in the uploads folder for good.
     *
     * @param \App\Model\Entity\Offer $offer
     * @return void
     */
    protected function remove(Offer $offer): void
    {
        $image = (string)$offer->image;

        if ($this->offersTable->delete($offer) && $image !== '') {
            // Only once the row is gone, so a failed delete keeps its picture.
            (new ImageUploadService())->delete($image);
        }
    }

    /**
     * The offers already mirroring a Square discount, keyed by its id.
     *
     * Rows with no Square id are the studio's own and are left out: they are
     * not candidates for an update, and must not be swept up by the deletion
     * pass at the end of a run.
     *
     * @return array<string, \App\Model\Entity\Offer>
     */
    protected function existingBySquareId(): array
    {
        $offers = $this->offersTable->find()
            ->where(['square_catalog_id IS NOT' => null])
            ->all();

        $bySquareId = [];

        foreach ($offers as $offer) {
            $bySquareId[(string)$offer->square_catalog_id] = $offer;
        }

        return $bySquareId;
    }
}
