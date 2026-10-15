<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Offer;
use App\Service\ImageUploadService;
use Cake\Event\EventInterface;
use Cake\Http\Response;

/**
 * Offers Controller
 *
 * Staff only throughout: offers are shown to the public as a block on the
 * home page, so there is no public page here to gate separately.
 *
 * Which offers exist is Square's decision. Every offer mirrors a discount
 * in Square's catalogue, so there is no Add - one made here would belong to
 * nothing and be removed at the next sync - and no Delete, because the
 * discount would still be in Square and the sync would put the offer
 * straight back. Taking one off the site is Hide.
 *
 * Editing stays, because a Square discount is only a name and a percentage.
 * The description, the small print, the picture and the before-and-after
 * prices have nowhere to live in Square, so they are written here and the
 * sync leaves them alone.
 *
 * @property \App\Model\Table\OffersTable $Offers
 */
class OffersController extends AppController
{
    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        $this->viewBuilder()->setLayout('admin');
    }

    /**
     * The management list.
     *
     * @return \Cake\Http\Response
     */
    public function manage(): Response
    {
        $this->requireAdmin();

        $this->set('offers', $this->Offers->forManagement()->all());

        return $this->render();
    }

    /*
     * add() was here. Every offer mirrors a Square discount now, so one
     * created here had no discount behind it and was removed by the next
     * sync - the button offered something the app could not keep.
     */

    /**
     * Fills in the half of an offer that Square has no room for.
     *
     * @param string|null $id
     * @return \Cake\Http\Response|null|void
     */
    public function edit(?string $id = null)
    {
        $this->requireAdmin();

        // Contained so the form can show the prices the offer works out from
        // its treatment, rather than two blank boxes.
        $offer = $this->Offers->get($id, contain: ['Services']);

        if ($this->request->is(['post', 'put', 'patch'])) {
            $offer = $this->Offers->patchEntity($offer, $this->normalise($this->request->getData()));

            /*
             * The picture is dealt with only once the words are known to be
             * valid, so a rejected form does not leave an orphaned file on
             * disk that nothing points at.
             */
            $imageError = null;

            if (!$offer->getErrors()) {
                $imageError = $this->applyImage($offer);
            }

            if ($imageError === null && $this->Offers->save($offer)) {
                $this->Flash->success('The offer has been updated.');

                return $this->redirect(['action' => 'manage']);
            }

            if ($imageError !== null) {
                // Keyed so it renders beside the file field that caused it.
                $this->Flash->error($imageError, ['key' => 'offerImage']);
            } else {
                $this->Flash->error('The offer could not be saved. Please check the fields below.');
            }
        }

        $this->set('offer', $offer);
        $this->set('treatments', $this->treatmentChoices());
    }

    /**
     * The treatments an offer can be about, for the picker.
     *
     * Hidden ones are included. A treatment can be off the public menu and
     * still be what a discount is for, and leaving it out would silently
     * clear the choice the next time anyone saved the offer.
     *
     * @return array<string, string>
     */
    protected function treatmentChoices(): array
    {
        $choices = [];

        $services = $this->fetchTable('Services')->find()
            ->orderBy(['category' => 'ASC', 'position' => 'ASC', 'name' => 'ASC'])
            ->all();

        foreach ($services as $service) {
            // Grouped by category, the way the menu itself reads.
            $choices[$service->category][$service->id] = $service->name
                . ($service->price_label === 'Price varies' ? '' : ' - ' . $service->price_label);
        }

        return $choices;
    }

    /**
     * Shows or hides one offer on the home page.
     *
     * This is what Delete used to be. The discount lives in Square, so a
     * deleted offer came straight back at the next sync; hiding is the
     * decision that actually sticks, and the one thing about an offer this
     * screen really decides.
     *
     * @param string|null $id
     * @return \Cake\Http\Response|null
     */
    public function toggleVisibility(?string $id = null): ?Response
    {
        $this->requireAdmin();
        $this->request->allowMethod(['post']);

        $offer = $this->Offers->get($id);
        $offer->visible = !$offer->visible;

        if ($this->Offers->save($offer)) {
            $this->Flash->success(sprintf(
                '%s is now %s on the home page.',
                $offer->title,
                $offer->visible ? 'shown' : 'hidden',
            ));
        } else {
            $this->Flash->error('That could not be changed.');
        }

        return $this->redirect(['action' => 'manage']);
    }

    /*
     * delete() was here. The offer is only this site's copy of a discount in
     * Square, so removing it changed nothing: the discount was still there
     * and the next sync recreated the offer. Deleting the discount in Square
     * is what removes an offer, and the sync then takes this copy with it.
     * Hiding is for taking one off the home page while keeping it.
     */

    /**
     * Stores a new order for the offers.
     *
     * @return \Cake\Http\Response|null
     */
    public function reorder(): ?Response
    {
        $this->requireAdmin();
        $this->request->allowMethod(['post']);

        $ids = (array)$this->request->getData('offer_order');

        if ($ids !== []) {
            $this->Offers->reorder($ids);
            $this->Flash->success('The order has been saved.');
        }

        return $this->redirect(['action' => 'manage']);
    }

    /**
     * Applies a picture upload or removal to an offer.
     *
     * @param \App\Model\Entity\Offer $offer
     * @return string|null An error to show, or null when nothing went wrong.
     */
    protected function applyImage(Offer $offer): ?string
    {
        $uploader = new ImageUploadService();
        $current = (string)$offer->image;

        if ($this->request->getData('image_remove')) {
            $uploader->delete($current);
            $offer->image = null;

            return null;
        }

        $file = $this->request->getUploadedFile('image');

        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $result = $uploader->store($file, 'offer');

        if (!$result['ok']) {
            return $result['error'];
        }

        $offer->image = $result['filename'];

        // Only once the replacement is safely stored.
        $uploader->delete($current);

        return null;
    }

    /**
     * Trims what the form collects, and takes the picture back out of it.
     *
     * The upload has to be removed before patching. `image` is a string
     * column, and the marshaller casts by column type before the accessible
     * list is consulted, so leaving the uploaded file under that key throws
     * on the cast rather than being quietly ignored. applyImage() handles the
     * file separately, straight off the request.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function normalise(array $data): array
    {
        $data['terms'] = trim((string)($data['terms'] ?? ''));
        $data['visible'] = !empty($data['visible']);

        /*
         * Prices are typed in dollars because that is how a price is written,
         * and kept in cents because that is how money should be stored.
         */
        $data['price_before_cents'] = $this->toCents($data['price_before'] ?? null);
        $data['price_after_cents'] = $this->toCents($data['price_after'] ?? null);

        /*
         * The name and the percentage are Square's and are shown read-only,
         * but a read-only field is a hint to the browser, not a rule - so
         * they are dropped here as well. Keeping them would let an edit
         * overwrite Square's until the next sync put them back, which reads
         * as the site losing the change at random.
         */
        unset(
            $data['title'],
            $data['discount_percent'],
            $data['image'],
            $data['image_remove'],
            $data['price_before'],
            $data['price_after'],
        );

        return $data;
    }

    /**
     * Turns a typed dollar amount into whole cents.
     *
     * @param mixed $value
     * @return int|null Null for a blank field, which means "not set" rather
     *  than zero.
     */
    protected function toCents(mixed $value): ?int
    {
        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        return (int)round((float)str_replace(['$', ','], '', $value) * 100);
    }
}
