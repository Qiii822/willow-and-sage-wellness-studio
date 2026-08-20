<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Mailer\Mailer;
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
            'resetPassword',
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

            if ($user) {
                $user->reset_token = bin2hex(random_bytes(32));
                $user->reset_token_expires = new \DateTimeImmutable('+1 hour');

                if (!$this->Users->save($user)) {
                    $this->Flash->error('We could not process your request. Please try again.');

                    return null;
                }

                $mailer = new Mailer('default');
                $mailer
                    ->setEmailFormat('both')
                    ->setTo($user->email)
                    ->setSubject('Reset your account password')
                    ->setViewVars([
                        'first_name' => $user->first_name,
                        'last_name' => $user->surname,
                        'email' => $user->email,
                        'nonce' => $user->reset_token,
                    ])
                    ->viewBuilder()
                    ->setTemplate('reset_password');

                if (!$mailer->deliver()) {
                    $this->Flash->error('We could not send the reset email. Please try again.');

                    return null;
                }
            }

            $this->Flash->success(
                'If an account exists for this email address, password reset instructions will be sent.'
            );

            return $this->redirect(['action' => 'login']);
        }
    }

    /**
     * Reset Password method
     *
     * @param string|null $token Password reset token.
     * @return \Cake\Http\Response|null|void
     */
    public function resetPassword(?string $token = null)
    {
        $this->viewBuilder()->setLayout('account');

        $user = $token === null ? null : $this->Users->find()
            ->where([
                'reset_token' => $token,
                'reset_token_expires >' => new \DateTimeImmutable(),
            ])
            ->first();

        if (!$user) {
            $this->Flash->error('This password reset link is invalid or has expired.');

            return $this->redirect(['action' => 'forgotPassword']);
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            $user = $this->Users->patchEntity(
                $user,
                $this->request->getData(),
                ['validate' => 'changePassword']
            );
            $user->reset_token = null;
            $user->reset_token_expires = null;

            if ($this->Users->save($user)) {
                $this->Flash->success('Your password has been updated.');

                return $this->redirect(['action' => 'login']);
            }

            $this->set('errors', $user->getErrors());
            $this->Flash->error('The password could not be updated. Please correct the errors below.');
        }

        $this->set(compact('user', 'token'));
    }


    // public function forgetPassword()
    // {
    //     if ($this->request->is('post')) {
    //         // Retrieve the user entity by provided email address
    //         // Note findBy* is a magic/dynamic finder
    //         $user = $this->Users->findByEmail($this->request->getData('email'))->first();
    //         if ($user) {
    //             // Set nonce and expiry date
    //             $user->nonce = Security::randomString(128);
    //             $user->nonce_expiry = new DateTime('7 days');
    //             if ($this->Users->save($user)) {
    //                 // Now let's send the password reset email
    //                 $mailer = new Mailer('default');

    //                 // email basic config
    //                 $mailer
    //                     ->setEmailFormat('both')
    //                     ->setTo($user->email)
    //                     ->setSubject('Reset your account password');

    //                 // select email template
    //                 $mailer
    //                     ->viewBuilder()
    //                     ->setTemplate('reset_password');

    //                 // transfer required view variables to email template
    //                 $mailer
    //                     ->setViewVars([
    //                         'first_name' => $user->first_name,
    //                         'last_name' => $user->last_name,
    //                         'nonce' => $user->nonce,
    //                         'email' => $user->email,
    //                     ]);

    //                 //Send email
    //                 if (!$mailer->deliver()) {
    //                     // Just in case something goes wrong when sending emails
    //                     $this->Flash->error('We have encountered an issue when sending you emails. Please try again. ');

    //                     return $this->render(); // Skip the rest of the controller and render the view
    //                 }
    //             } else {
    //                 // Just in case something goes wrong when saving nonce and expiry
    //                 $this->Flash->error('We are having issue to reset your password. Please try again. ');

    //                 return $this->render(); // Skip the rest of the controller and render the view
    //             }
    //         }

    //         /*
    //          * **This is a bit of a special design**
    //          * We don't tell the user if their account exists, or if the email has been sent,
    //          * because it may be used by someone with malicious intent. We only need to tell
    //          * the user that they'll get an email.
    //          */
    //         $this->Flash->success(
    //             'Please check your inbox (or spam folder) for ' .
    //             'an email regarding how to reset your account password. ',
    //         );

    //         return $this->redirect(['action' => 'login']);
    //     }
    // }


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
     * Edit User method
     *
     * Admins can edit normal users and themselves.
     * Superadmins can edit all users.
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null|void
     */
    public function edit(?string $id = null)
    {
        $this->requireAdmin();

        $identity = $this->request->getAttribute('identity');

        $currentUserId = $identity->id ?? null;
        $currentRole = $identity->roles ?? $identity->role ?? null;

        $user = $this->Users->get($id);

        $targetRole = $user->roles ?? $user->role ?? null;

        $isEditingSelf = (string)$currentUserId === (string)$user->id;

        // Admins can only edit normal users or themselves.
        if ($currentRole === 'admin') {
            $isEditingNormalUser = $targetRole === 'user';

            if (!$isEditingSelf && !$isEditingNormalUser) {
                throw new \Cake\Http\Exception\ForbiddenException(
                    'You do not have permission to edit this user.'
                );
            }
        }

        $isSuperAdmin = $currentRole === 'superadmin';

        if ($this->request->is(['patch', 'post', 'put'])) {

            $data = $this->request->getData();

            // Only superadmins can change roles, and never their own,
            // nobody should be able to change their own role
            if (!$isSuperAdmin || $isEditingSelf) {
                unset($data['roles']);
            }

            $user = $this->Users->patchEntity(
                $user,
                $data
            );

            if ($this->Users->save($user)) {
                $this->Flash->success(
                    'The user has been updated.'
                );

                return $this->redirect([
                    'controller' => 'Users',
                    'action' => 'view',
                    $user->id
                ]);
            }

            $this->Flash->error(
                'The user could not be updated. Please try again.'
            );
        }

        $this->set(compact('user', 'isSuperAdmin', 'isEditingSelf'));
    }

    /**
     * Add User method
     *
     * Admins can create normal users.
     * Superadmins can create users, admins and superadmins.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function add()
    {
        $this->requireAdmin();

        $identity = $this->request->getAttribute('identity');
        $currentRole = $identity->roles ?? $identity->role ?? null;

        $isSuperAdmin = $currentRole === 'superadmin';

        $user = $this->Users->newEmptyEntity();

        if ($this->request->is('post')) {

            $data = $this->request->getData();

            /*
             * Admins may only create normal users.
             * Superadmins may choose any role.
             */
            if (!$isSuperAdmin) {
                $data['roles'] = 'user';
            }

            $user = $this->Users->patchEntity(
                $user,
                $data
            );

            if ($this->Users->save($user)) {
                $this->Flash->success(
                    'The user has been created.'
                );

                return $this->redirect([
                    'controller' => 'Users',
                    'action' => 'index'
                ]);
            }

            $errors = $user->getErrors();
            $this->set(compact('errors'));

            $this->Flash->error(
                'The user could not be created. Please correct the errors below.'
            );
        }

        $this->set(compact(
            'user',
            'isSuperAdmin'
        ));
    }

    /**
     * Delete User method
     *
     * Only superadmins can delete users.
     *
     * @param string|null $id User id.
     * @return \Cake\Http\Response|null
     */
    public function delete(?string $id = null)
    {
        $this->requireAdmin();

        $identity = $this->request->getAttribute('identity');
        $currentRole = $identity->roles ?? $identity->role ?? null;

        // Only superadmins can delete users.
        if ($currentRole !== 'superadmin') {
            throw new \Cake\Http\Exception\ForbiddenException(
                'Only superadmins can delete users.'
            );
        }

        $this->request->allowMethod(['post', 'delete']);

        $user = $this->Users->get($id);

        if ($this->Users->delete($user)) {
            $this->Flash->success(
                'The user has been deleted.'
            );
        } else {
            $this->Flash->error(
                'The user could not be deleted. Please try again.'
            );
        }

        return $this->redirect([
            'controller' => 'Users',
            'action' => 'index'
        ]);
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
