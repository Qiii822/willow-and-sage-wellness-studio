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

    /**
     * @return \Cake\Http\Response|null|void
     */
    public function add()
    {
        $this->requireAdmin();

        $offer = $this->Offers->newEmptyEntity();

        if ($this->request->is('post')) {
            $offer = $this->Offers->patchEntity($offer, $this->normalise($this->request->getData()));
            $offer->position = $this->Offers->nextPosition();

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
                $this->Flash->success('The offer has been added.');

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
    }

    /**
     * @param string|null $id
     * @return \Cake\Http\Response|null|void
     */
    public function edit(?string $id = null)
    {
        $this->requireAdmin();

        $offer = $this->Offers->get($id);

        if ($this->request->is(['post', 'put', 'patch'])) {
            $offer = $this->Offers->patchEntity($offer, $this->normalise($this->request->getData()));

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

        // Same form as adding; only the heading and target differ.
        $this->viewBuilder()->setTemplate('add');
    }

    /**
     * @param string|null $id
     * @return \Cake\Http\Response|null
     */
    public function delete(?string $id = null): ?Response
    {
        $this->requireAdmin();
        $this->request->allowMethod(['post', 'delete']);

        $offer = $this->Offers->get($id);
        $image = $offer->image;

        if ($this->Offers->delete($offer)) {
            // Only after the row is gone, so a failed delete keeps its picture.
            (new ImageUploadService())->delete($image);

            $this->Flash->success('The offer has been removed.');
        } else {
            $this->Flash->error('The offer could not be removed.');
        }

        return $this->redirect(['action' => 'manage']);
    }

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
        $data['title'] = trim((string)($data['title'] ?? ''));
        $data['terms'] = trim((string)($data['terms'] ?? ''));
        $data['visible'] = !empty($data['visible']);

        /*
         * Prices are typed in dollars because that is how a price is written,
         * and kept in cents because that is how money should be stored.
         */
        $data['price_before_cents'] = $this->toCents($data['price_before'] ?? null);
        $data['price_after_cents'] = $this->toCents($data['price_after'] ?? null);

        $percent = trim((string)($data['discount_percent'] ?? ''));
        $data['discount_percent'] = $percent === '' ? null : (int)$percent;

        unset($data['image'], $data['image_remove'], $data['price_before'], $data['price_after']);

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
