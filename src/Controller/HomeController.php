<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Home Controller
 *
 * Customer facing landing page. Public so visitors can browse it before
 * signing in, and the page non-admins land on after login.
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

        $this->set(compact('identity'));

        return $this->render();
    }
}
