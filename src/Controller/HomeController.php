<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Home Controller
 *
 * Customer-facing public landing page. Visitors can browse it without
 * signing in, while signed-in users can still be identified by the view.
 */
class HomeController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['index']);

        $this->viewBuilder()->setLayout('site');
    }

    public function index(): Response
    {
        $identity = $this->request->getAttribute('identity');

        $sections = $this->fetchTable('SiteSections')->visibleLayout();

        $this->set(compact('identity', 'sections'));

        return $this->render();
    }
}
