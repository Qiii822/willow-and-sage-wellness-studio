<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Mailer\Mailer;
use App\Service\SquareAuthService;
use App\Service\Sync\CustomerLinkService;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{
    /**
     * Roles that count as staff. The admin list shows these and nothing
     * else; everyone on the `user` role is a customer.
     *
     * @var list<string>
     */
    protected const STAFF_ROLES = ['admin', 'superadmin'];

    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        // Configure the login action to not require authentication, preventing
        // the infinite redirect loop issue
        $this->Authentication->allowUnauthenticated([
            'login',
            'register',
            'forgotPassword',
            'resetPassword',
        ]);

        // Admin screens share the dashboard shell so the sidebar navigation
        // stays visible when moving between them.
        $adminLayoutActions = [
            'index',
            'view',
            'edit',
            'add',
            'settings',
            'profile',
        ];

        if (in_array(
            $this->request->getParam('action'),
            $adminLayoutActions,
            true
        )) {
            $this->viewBuilder()->setLayout('admin');
        }

        /*
         * This controller mixes staff screens with ones every signed-in user
         * needs (profile, password changes), so the gate is per-action rather
         * than the controller-wide $adminOnly flag. It sends customers to the
         * login page the same way the other staff-only controllers do; the
         * requireAdmin() calls inside the actions remain as a second check.
         */
        $staffOnlyActions = [
            'index',
            'view',
            'edit',
            'add',
            'delete',
            'settings',
        ];

        if (in_array(
            $this->request->getParam('action'),
            $staffOnlyActions,
            true
        )) {
            $this->blockNonAdmin($event);
        }
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
                : ['controller' => 'Home', 'action' => 'index'];
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
     * Register method
     *
     * Public self-service signup. This route can only ever produce a normal
     * customer account: the role and active flag are set here and any values
     * posted for them are discarded, so a crafted form cannot mint an admin.
     * Staff accounts are created from the admin screens (see add()).
     *
     * @return \Cake\Http\Response|null|void
     */
    public function register()
    {
        $this->request->allowMethod(['get', 'post']);

        // Nothing to sign up for while already signed in.
        if ($this->request->getAttribute('identity')) {
            return $this->redirect(['controller' => 'Home', 'action' => 'index']);
        }

        $user = $this->Users->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            $data['roles'] = 'user';
            $data['active'] = true;

            $user = $this->Users->patchEntity($user, $data);

            if ($this->Users->save($user)) {
                /*
                 * Someone who has booked with the studio before is already in
                 * the Square mirror, so their new account can be matched right
                 * away rather than waiting for the next sync. Never let a
                 * linking problem block a signup that already succeeded.
                 */
                try {
                    (new CustomerLinkService())->linkUser($user);
                } catch (\Throwable $e) {
                    \Cake\Log\Log::error(
                        'Could not link new account to a Square customer: ' . $e->getMessage()
                    );
                }

                $this->Flash->success(
                    'Your account has been created. Please sign in.'
                );

                return $this->redirect(['action' => 'login']);
            }

            $this->set('errors', $user->getErrors());

            $this->Flash->error(
                'We could not create your account. Please correct the errors below.'
            );
        }

        $this->set(compact('user'));
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

        $search = trim((string)$this->request->getQuery('q', ''));

        /*
         * Staff accounts only. Customers hold the `user` role and are managed
         * from the dashboard's customer screens instead, alongside the
         * customer records synced from Square.
         */
        $query = $this->Users
            ->find()
            ->where(['Users.roles IN' => self::STAFF_ROLES])
            ->orderBy([
                'Users.first_name' => 'ASC',
                'Users.surname' => 'ASC',
            ]);

        if ($search !== '') {
            /*
             * Escape the LIKE wildcards so a literal % or _ typed by the user
             * is matched as a character rather than treated as a pattern.
             * The value itself is parameter-bound by the ORM.
             */
            $like = '%' . addcslashes($search, '%_\\') . '%';

            $conditions = [
                ['Users.first_name LIKE' => $like],
                ['Users.surname LIKE' => $like],
                ['Users.email LIKE' => $like],
            ];

            // "Eric Cao" should also match first name + surname across columns.
            $parts = preg_split('/\s+/', $search, 2);

            if (count($parts) === 2 && $parts[1] !== '') {
                $conditions[] = [
                    'Users.first_name LIKE' => '%' . addcslashes($parts[0], '%_\\') . '%',
                    'Users.surname LIKE' => '%' . addcslashes($parts[1], '%_\\') . '%',
                ];
            }

            $query->where(['OR' => $conditions]);
        }

        /*
         * Filters. Every value is checked against a whitelist or a strict
         * format before it reaches the query, and anything unrecognised is
         * reset to an empty string so the form redisplays a clean state
         * rather than echoing back whatever was in the URL.
         */

        $role = (string)$this->request->getQuery('role', '');
        $status = (string)$this->request->getQuery('status', '');
        $createdFrom = trim((string)$this->request->getQuery('created_from', ''));
        $createdTo = trim((string)$this->request->getQuery('created_to', ''));

        // Only staff roles are selectable here; anything else narrows to
        // nothing, so treat it as no filter rather than showing an empty page.
        if (in_array($role, self::STAFF_ROLES, true)) {
            $query->where(['Users.roles' => $role]);
        } else {
            $role = '';
        }

        if ($status === 'active' || $status === 'inactive') {
            $query->where(['Users.active' => $status === 'active']);
        } else {
            $status = '';
        }

        $isDate = static fn (string $value): bool
            => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);

        if (!$isDate($createdFrom)) {
            $createdFrom = '';
        }

        if (!$isDate($createdTo)) {
            $createdTo = '';
        }

        /*
         * Tolerate a reversed range. ISO dates sort correctly as plain
         * strings, so comparing them directly is enough to spot the swap.
         * Correcting it beats returning zero rows for what is obviously
         * just the two fields filled in the other order.
         */
        if ($createdFrom !== '' && $createdTo !== '' && $createdFrom > $createdTo) {
            [$createdFrom, $createdTo] = [$createdTo, $createdFrom];
        }

        if ($createdFrom !== '') {
            $query->where(['Users.created_at >=' => $createdFrom . ' 00:00:00']);
        }

        if ($createdTo !== '') {
            $query->where(['Users.created_at <=' => $createdTo . ' 23:59:59']);
        }

        $hasFilters =
            $search !== ''
            || $role !== ''
            || $status !== ''
            || $createdFrom !== ''
            || $createdTo !== '';

        $users = $query->all();

        $this->set(compact(
            'users',
            'search',
            'role',
            'status',
            'createdFrom',
            'createdTo',
            'hasFilters'
        ));
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

                /*
                 * Customers are not listed on the admin screen, so send the
                 * creator to whichever list the new account shows up on.
                 */
                if (in_array($user->roles, self::STAFF_ROLES, true)) {
                    return $this->redirect([
                        'controller' => 'Users',
                        'action' => 'index'
                    ]);
                }

                return $this->redirect([
                    'controller' => 'Customers',
                    'action' => 'index',
                    '?' => ['source' => 'registered'],
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
