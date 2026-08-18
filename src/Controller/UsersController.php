<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\SquareAuthService;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        // Configure the login action to not require authentication, preventing
        // the infinite redirect loop issue
        $this->Authentication->allowUnauthenticated([
            'login',
            'forgotPassword',
        ]);
    }

    /**
     * Login method
     *
     * @return \Cake\Http\Response|null|void Redirect to location before authentication
     */
    public function login()
    {
        $this->request->allowMethod(['get', 'post']);
        $result = $this->Authentication->getResult();

        // if user passes authentication, grant access to the system
        if ($result && $result->isValid()) {
            $identity = $this->Authentication->getIdentity();
            $role = $identity->roles ?? $identity->role ?? null;
            $fallbackLocation = $role === 'admin' || $role === 'superadmin'
                ? '/dashboard'
                : ['controller' => 'Dashboard', 'action' => 'index'];
            // Always use role-based redirect, unless user was trying to access a specific page
            $loginRedirect = $this->Authentication->getLoginRedirect();
            $isDefaultRedirect = !$loginRedirect || str_contains((string)$loginRedirect, '/login');
            return $this->redirect($isDefaultRedirect ? $fallbackLocation : $loginRedirect);
        }

        // display error if user submitted their credentials but authentication failed
        if ($this->request->is('post') && !$result->isValid()) {
            $this->Flash->error('Email address and/or Password is incorrect. Please try again. ');
        }
    }

    /**
     * Forgot Password method
     *
     * @return \Cake\Http\Response|null|void
     */
    public function forgotPassword()
    {
        $this->viewBuilder()->setLayout('account');

        $this->request->allowMethod(['get', 'post']);

        if ($this->request->is('post')) {
            $email = trim((string)$this->request->getData('email'));

            $user = $this->Users->find()
                ->where(['email' => $email])
                ->first();


            // TODO: Add password reset token and email functionality.


            if ($user) {
                $this->Flash->success(
                    'If an account exists for this email address, password reset instructions will be sent.'
                );
            } else {
                $this->Flash->success(
                    'If an account exists for this email address, password reset instructions will be sent.'
                );
            }
        }
    }

    public function logout()
    {
        $this->Authentication->logout();
        return $this->redirect(['controller' => 'Users', 'action' => 'login']);
    }

    public function profile(): void
    {
        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            throw new \Cake\Http\Exception\UnauthorizedException('Please login to view your profile.');
        }

        $this->set('identity', $identity);
    }
    /**
     * User Index method
     *
     * Lists all local users.
     * Admin and superadmin only.
     *
     * @return void
     */
    public function index(): void
    {
        $this->requireAdmin();

        $users = $this->Users
            ->find()
            ->orderBy([
                'Users.first_name' => 'ASC',
                'Users.surname' => 'ASC',
            ])
            ->all();

        $this->set(compact('users'));
    }


    /**
     * View User method
     *
     * Displays a single local user.
     * Admin and superadmin only.
     *
     * @param string|null $id User id.
     * @return void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException
     */
    public function view(?string $id = null): void
    {
        $this->requireAdmin();

        $user = $this->Users->get($id);

        $this->set(compact('user'));
    }


    /**
     * Change Password method
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function changePassword(?string $id = null)
    {
        $this->viewBuilder()->setLayout('account');

        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            throw new \Cake\Http\Exception\UnauthorizedException('Please login to change your password.');
        }

        if ($id === null) {
            $id = $identity->id ?? null;
        }

        $user = $this->Users->get($id, ['contain' => []]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $user = $this->Users->patchEntity($user, $this->request->getData(), ['validate' => 'changePassword']);
            if ($this->Users->save($user)) {
                $this->Flash->success('Your password has been updated.');

                return $this->redirect(['controller' => 'Users', 'action' => 'profile']);
            }

            // Persist validation errors to the view so the template can show reasons why save failed
            $errors = $user->getErrors();
            $this->set(compact('errors'));
            $this->Flash->error('The password could not be updated. Please correct the errors below.');
        }

        $this->set(compact('user'));
    }

    /**
     * Admin settings page: shows and manages the app's Square connection
     * (connect/disconnect/sync status). Admin-only.
     *
     * @return void
     */
    public function settings(): void
    {
        $this->requireAdmin();

        $squareAuth = new SquareAuthService();
        $squareIdentity = $squareAuth->getActiveIdentity();

        $importsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Imports');
        $lastImport = $importsTable->getLastCompleted();
        $lastSyncedAt = $lastImport?->finished_at;

        $this->set('squareIdentity', $squareIdentity);
        $this->set('lastSyncedAt', $lastSyncedAt);
        $this->set('justConnected', (bool)$this->request->getQuery('connected'));
    }

}
