<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

/**
 * Home Controller
 *
 * The studio's public landing page, and on this branch the site root. It
 * needs no identity: nothing on the page changes according to who is
 * looking, because there is no signing in here.
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
        return $this->render();
    }
}
