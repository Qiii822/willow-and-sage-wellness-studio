<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /**
     * Controllers whose every action is staff-only set this to true.
     *
     * Signed-in accounts without an admin role are sent back to the customer
     * home page rather than shown a 403, because they arrive at these URLs by
     * typing them or following a stale bookmark, not by doing anything wrong.
     *
     * @var bool
     */
    protected bool $adminOnly = false;

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authentication.Authentication');

        $this->loadComponent('Flash');

        
        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    protected function requireAdmin(): void
    {
        $user = $this->request->getAttribute('identity');
        $role = $user->roles ?? $user->role ?? null;

        if (!$user || ($role !== 'admin' && $role !== 'superadmin')) {
            throw new \Cake\Http\Exception\ForbiddenException('Admin access only.');
        }
    }

    /**
     * Returns true when the given identity holds a staff role.
     *
     * @param mixed $identity The request's identity, or null.
     * @return bool
     */
    protected function isAdminIdentity(mixed $identity): bool
    {
        if (!$identity) {
            return false;
        }

        $role = $identity->roles ?? $identity->role ?? null;

        return $role === 'admin' || $role === 'superadmin';
    }

    /**
     * Sends a signed-in non-admin away from a staff-only controller.
     *
     * Requests with no identity at all are left alone so the Authentication
     * component can redirect them to the login page as usual.
     *
     * The session is ended rather than just redirected: sending a still
     * signed-in customer to /login would bounce straight back, because the
     * login action treats an existing valid session as a completed login.
     *
     * @param \Cake\Event\EventInterface $event The Controller.initialize event.
     * @return void
     */
    protected function blockNonAdmin(\Cake\Event\EventInterface $event): void
    {
        $identity = $this->request->getAttribute('identity');

        if (!$identity || $this->isAdminIdentity($identity)) {
            return;
        }

        $this->Authentication->logout();

        // Written after the logout so it cannot be dropped if clearing the
        // identity renews the session.
        $this->Flash->error(
            'That page is only available to staff accounts. '
            . 'You have been signed out - please sign in with a staff account.'
        );

        $event->setResult($this->redirect([
            'controller' => 'Users',
            'action' => 'login',
        ]));
        $event->stopPropagation();
    }

    protected function requireSuperAdmin(): void
    {
        $user = $this->request->getAttribute('identity');
        $role = $user->roles ?? $user->role ?? null;

        if (!$user || $role !== 'superadmin') {
            throw new \Cake\Http\Exception\ForbiddenException('Super Admin access only.');
        }
    }
 
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        // for all controllers in our application, make index and view
        // actions public, skipping the authentication check
        // $this->Authentication->allowUnauthenticated(['index', 'view']);

        if ($this->adminOnly) {
            $this->blockNonAdmin($event);
        }
    }
}
