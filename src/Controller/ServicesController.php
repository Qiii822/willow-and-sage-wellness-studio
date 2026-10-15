<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\ServicesTable;
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
    /**
     * Actions only staff may reach.
     *
     * Adding, editing and deleting used to be here. The menu comes entirely
     * from Square now, and every sync replaces names, descriptions, prices,
     * durations and categories with Square's - so those screens offered
     * edits that were quietly undone, and a treatment added here vanished
     * at the next sync. They were a way to lose work, not to do any.
     *
     * What is left is the two things Square has no way to express: whether
     * a treatment is shown, and the order the menu runs in.
     */
    protected const STAFF_ONLY = [
        'manage',
        'reorder',
        'toggleVisibility',
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

        /*
         * So a treatment with an offer running on it is quoted at the offer
         * price here too, and the menu cannot end up disagreeing with the
         * booking page about what something costs.
         */
        $this->set('offers', $this->fetchTable('Offers')->currentByServiceId());

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
     * Shows or hides one treatment on the public menu.
     *
     * The only thing about a treatment this screen still decides. It used
     * to be a checkbox buried in the edit form, alongside fields the sync
     * overwrote - now it is the one control, where it can be seen.
     *
     * @param string|null $id
     * @return \Cake\Http\Response|null
     */
    public function toggleVisibility(?string $id = null): ?Response
    {
        $this->requireAdmin();
        $this->request->allowMethod(['post']);

        $service = $this->Services->get($id);
        $service->visible = !$service->visible;

        if ($this->Services->save($service)) {
            $this->Flash->success(sprintf(
                '%s is now %s on the menu.',
                $service->name,
                $service->visible ? 'shown' : 'hidden',
            ));
        } else {
            $this->Flash->error('That could not be changed.');
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

    /*
     * addCategory() and deleteCategory() were here. Every sync replaces the
     * category list with Square's, so one added here disappeared at the
     * next one and one removed came straight back - the buttons described
     * something the app could not actually do. Categories are made in
     * Square.
     *
     * normalise() and pickableCategories() went with the add/edit form they
     * served.
     */
}
