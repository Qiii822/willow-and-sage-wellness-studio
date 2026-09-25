<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\ServicesTable;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\Utility\Hash;

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
    protected const STAFF_ONLY = [
        'manage',
        'add',
        'edit',
        'delete',
        'reorder',
        'addCategory',
        'deleteCategory',
    ];

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
     * The public treatment menu, optionally narrowed to one category.
     *
     * The filter is a real query parameter rather than something only the
     * browser knows about, so a narrowed menu can be linked to and still
     * works with scripting turned off. The page then enhances it into an
     * instant client-side filter.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        $menu = $this->Services->menu();
        $categories = array_keys($menu);

        // An unrecognised category shows the whole menu rather than an error
        // page: a stale link should still land somewhere useful.
        $active = ServicesTable::categoryFromSlug(
            $this->request->getQuery('category'),
            $categories,
        );

        $this->set('menu', $menu);
        $this->set('categories', $categories);
        $this->set('activeCategory', $active);

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

        $services = $this->Services->forManagement()->all();

        $this->set('services', $services);

        /*
         * How many rows Square owns. Zero means nothing has been synced yet,
         * and warning about a sync overwriting edits would only confuse.
         */
        $this->set(
            'syncedCount',
            $this->Services->find()->where(['square_catalog_id IS NOT' => null])->count(),
        );

        $this->set('categories', $this->categoryOverview());

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
        $this->set('categories', $this->pickableCategories());
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
        $this->set('categories', $this->pickableCategories());

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
     * Every category the menu knows about, and what is filed under each.
     *
     * Both sources are shown: the ones on the managed list, and the ones
     * only a treatment mentions. A category that arrived from Square
     * before it was registered is still in use on the public page, so
     * leaving it out of this panel would make it invisible - present in
     * the menu, absent from the screen that is supposed to manage it.
     *
     * Only a registered category has an id, and only an unused one can be
     * removed, so the two together decide whether Remove is offered.
     *
     * @return array<int, array{name: string, id: string|null, used: int}>
     */
    protected function categoryOverview(): array
    {
        $usage = [];

        foreach ($this->Services->find()->all() as $service) {
            $usage[$service->category] = ($usage[$service->category] ?? 0) + 1;
        }

        $overview = [];

        foreach ($this->fetchTable('ServiceCategories')->find()->all() as $category) {
            $overview[$category->name] = [
                'name' => $category->name,
                'id' => $category->id,
                'used' => $usage[$category->name] ?? 0,
            ];
        }

        foreach ($usage as $name => $count) {
            if (!isset($overview[$name])) {
                $overview[$name] = ['name' => $name, 'id' => null, 'used' => $count];
            }
        }

        ksort($overview);

        return array_values($overview);
    }

    /**
     * The categories a treatment may be filed under.
     *
     * The managed list, plus anything already in use that is not on it -
     * a category can arrive from Square between syncs registering it, and
     * dropping it from the picker would quietly move those treatments.
     *
     * @return array<int, string>
     */
    protected function pickableCategories(): array
    {
        $names = $this->fetchTable('ServiceCategories')->names();

        foreach ($this->Services->categories() as $inUse) {
            if (!in_array($inUse, $names, true)) {
                $names[] = $inUse;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Adds a category to the list treatments can be filed under.
     *
     * @return \Cake\Http\Response|null
     */
    public function addCategory(): ?Response
    {
        $this->requireAdmin();
        $this->request->allowMethod(['post']);

        $categories = $this->fetchTable('ServiceCategories');

        $category = $categories->newEntity([
            'name' => trim((string)$this->request->getData('name')),
        ]);

        if ($categories->save($category)) {
            $this->Flash->success('The category has been added.');
        } else {
            /*
             * Reported rather than swallowed: a duplicate name and a blank
             * one are both easy to hit, and a silent no-op would look like
             * the button was broken.
             */
            $errors = Hash::flatten($category->getErrors());

            $this->Flash->error(
                $errors !== []
                    ? (string)reset($errors)
                    : 'The category could not be added.',
            );
        }

        return $this->redirect(['action' => 'manage']);
    }

    /**
     * Removes a category from the list.
     *
     * Only when nothing is filed under it. Deleting one that is in use
     * would leave those treatments pointing at a heading that is no longer
     * offered, and the public page grouping them under it regardless.
     *
     * @param string|null $id
     * @return \Cake\Http\Response|null
     */
    public function deleteCategory(?string $id = null): ?Response
    {
        $this->requireAdmin();
        $this->request->allowMethod(['post', 'delete']);

        $categories = $this->fetchTable('ServiceCategories');
        $category = $categories->get($id);

        $inUse = $this->Services->find()
            ->where(['category' => $category->name])
            ->count();

        if ($inUse > 0) {
            $this->Flash->error(sprintf(
                'Cannot remove "%s" - %d treatment%s still filed under it.',
                $category->name,
                $inUse,
                $inUse === 1 ? ' is' : 's are',
            ));

            return $this->redirect(['action' => 'manage']);
        }

        if ($categories->delete($category)) {
            $this->Flash->success('The category has been removed.');
        } else {
            $this->Flash->error('The category could not be removed.');
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
