<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;
use Cake\Http\Response;

/**
 * Services Controller
 *
 * Holds both halves of the treatment menu: the public page anyone can read,
 * and the screens staff use to maintain it. They are gated per action rather
 * than per controller, since only `index` is public.
 *
 * @property \App\Model\Table\ServicesTable $Services
 */
class ServicesController extends AppController
{
    /** Actions only staff may reach. */
    protected const STAFF_ONLY = ['manage', 'add', 'edit', 'delete', 'reorder'];

    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['index']);

        $action = $this->request->getParam('action');

        $this->viewBuilder()->setLayout(
            in_array($action, self::STAFF_ONLY, true) ? 'admin' : 'site',
        );
    }

    /**
     * The public treatment menu.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        $this->set('menu', $this->Services->menu());

        return $this->render();
    }

    /**
     * The management list.
     *
     * @return \Cake\Http\Response
     */
    public function manage(): Response
    {
        $this->requireAdmin();

        $this->set('services', $this->Services->forManagement()->all());

        return $this->render();
    }

    /**
     * @return \Cake\Http\Response|null|void
     */
    public function add()
    {
        $this->requireAdmin();

        $service = $this->Services->newEmptyEntity();

        if ($this->request->is('post')) {
            $service = $this->Services->patchEntity(
                $service,
                $this->normalise($this->request->getData()),
            );

            $service->position = $this->Services->nextPosition();

            if ($this->Services->save($service)) {
                $this->Flash->success('The treatment has been added.');

                return $this->redirect(['action' => 'manage']);
            }

            $this->Flash->error('The treatment could not be saved. Please check the fields below.');
        }

        $this->set('service', $service);
        $this->set('categories', $this->Services->categories());
    }

    /**
     * @param string|null $id
     * @return \Cake\Http\Response|null|void
     */
    public function edit(?string $id = null)
    {
        $this->requireAdmin();

        $service = $this->Services->get($id);

        if ($this->request->is(['post', 'put', 'patch'])) {
            $service = $this->Services->patchEntity(
                $service,
                $this->normalise($this->request->getData()),
            );

            if ($this->Services->save($service)) {
                $this->Flash->success('The treatment has been updated.');

                return $this->redirect(['action' => 'manage']);
            }

            $this->Flash->error('The treatment could not be saved. Please check the fields below.');
        }

        $this->set('service', $service);
        $this->set('categories', $this->Services->categories());

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

        $service = $this->Services->get($id);

        if ($this->Services->delete($service)) {
            $this->Flash->success('The treatment has been removed.');
        } else {
            $this->Flash->error('The treatment could not be removed.');
        }

        return $this->redirect(['action' => 'manage']);
    }

    /**
     * Stores a new order for the menu.
     *
     * @return \Cake\Http\Response|null
     */
    public function reorder(): ?Response
    {
        $this->requireAdmin();
        $this->request->allowMethod(['post']);

        $ids = (array)$this->request->getData('service_order');

        if ($ids !== []) {
            $this->Services->reorder($ids);
            $this->Flash->success('The menu order has been saved.');
        }

        return $this->redirect(['action' => 'manage']);
    }

    /**
     * Turns what the form collects into what the table stores.
     *
     * The price is typed in dollars because that is how a menu is written;
     * it is kept in cents because that is how money should be stored.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function normalise(array $data): array
    {
        $price = trim((string)($data['price'] ?? ''));

        $data['price_cents'] = $price === ''
            ? null
            : (int)round((float)str_replace(['$', ','], '', $price) * 100);

        $duration = trim((string)($data['duration_minutes'] ?? ''));
        $data['duration_minutes'] = $duration === '' ? null : (int)$duration;

        $data['category'] = trim((string)($data['category'] ?? ''));
        $data['visible'] = !empty($data['visible']);

        unset($data['price']);

        return $data;
    }
}
