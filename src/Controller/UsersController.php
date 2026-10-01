<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Mailer\Mailer;
use App\Model\Table\VerificationCodesTable;
use App\Service\ContactChangeRevertService;
use App\Service\MailerFactory;
use App\Service\ContactVerificationService;
use App\Service\SquareAuthService;
use App\Service\Sync\CustomerLinkService;
use App\Utility\PhoneNumber;

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

    /**
     * Where the Authentication plugin's session authenticator keeps the
     * signed-in identity. Matches SessionAuthenticator's `sessionKey`
     * default, which this app does not override.
     *
     * @var string
     */
    protected const IDENTITY_SESSION_KEY = 'Auth';

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
            // Reached from a warning email by someone who may have lost
            // access to the account; the token in the link is the authority.
            'revertContactChange',
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
            'saveEmailContent',
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

            if ($user && !$this->sendPasswordResetEmail($user)) {
                $this->Flash->error('We could not send the reset email. Please try again.');

                return null;
            }

            $this->Flash->success(
                'If an account exists for this email address, password reset instructions will be sent.'
            );

            return $this->redirect(['action' => 'login']);
        }
    }

    /**
     * Issues a fresh reset token for an account and emails the link.
     *
     * Shared by the public "forgot password" form and the signed-in account
     * settings page, so both routes hand out tokens with the same lifetime
     * and send the same message.
     *
     * @param \App\Model\Entity\User $user The account to send to.
     * @return bool False if the token could not be stored or the mail could
     *   not be handed to the transport.
     */
    protected function sendPasswordResetEmail(\App\Model\Entity\User $user): bool
    {
        $user->reset_token = bin2hex(random_bytes(32));
        $user->reset_token_expires = new \DateTimeImmutable('+1 hour');

        if (!$this->Users->save($user)) {
            return false;
        }

        /*
         * Straight after an email change the reset goes to the address that
         * was replaced, not the new one. Someone who had just redirected the
         * account to themselves would otherwise be able to reset the
         * password immediately, before the warning email had been read. The
         * real owner still receives the link; whoever made the change does
         * not.
         */
        $destination = $this->fetchTable('ContactChangeReverts')
            ->addressDuringCooldown($user->id)
            ?? $user->email;

        $mailer = MailerFactory::create();
        $mailer
            ->setEmailFormat('both')
            ->setTo($destination)
            ->setSubject('Reset your account password')
            ->setViewVars([
                'first_name' => $user->first_name,
                'last_name' => $user->surname,
                'email' => $user->email,
                'nonce' => $user->reset_token,
            ])
            ->viewBuilder()
            ->setTemplate('reset_password');

        try {
            return (bool)$mailer->deliver();
        } catch (\Throwable $e) {
            // A misconfigured transport throws rather than returning false.
            \Cake\Log\Log::error('Password reset email failed: ' . $e->getMessage());

            return false;
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

    /**
     * Account Settings method
     *
     * The customer-facing counterpart to the admin `settings` action: it
     * manages the signed-in person's own contact details and password, and
     * is reachable by every signed-in user rather than staff only.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function accountSettings()
    {
        $identity = $this->request->getAttribute('identity');

        if (!$identity) {
            throw new \Cake\Http\Exception\UnauthorizedException(
                'Please login to manage your account.'
            );
        }

        // Shares the public site's shell, so the header and account menu
        // stay put when a customer arrives here from the home page.
        $this->viewBuilder()->setLayout('site');

        $user = $this->Users->get($identity->id);

        /*
         * Set before the handlers run: a handler that rejects its input
         * returns null so the page renders again with its errors, and the
         * template still needs to know about any outstanding codes.
         */
        $codesTable = $this->fetchTable('VerificationCodes');

        $this->set('pendingEmail', $codesTable->findPending(
            $user->id,
            VerificationCodesTable::CHANNEL_EMAIL
        ));
        $this->set('pendingSms', $codesTable->findPending(
            $user->id,
            VerificationCodesTable::CHANNEL_SMS
        ));
        $this->set('smsIsSimulated', (new ContactVerificationService())->smsIsSimulated());
        $this->set(compact('user', 'identity'));

        if ($this->request->is(['patch', 'post', 'put'])) {
            switch ($this->request->getData('form')) {
                case 'password':
                    return $this->updateOwnPassword($user);

                case 'password_email':
                    return $this->emailOwnPasswordReset($user);

                case 'verify_code':
                    return $this->confirmContactChange($user);

                case 'cancel_verification':
                    return $this->cancelContactChange($user);

                default:
                    return $this->updateOwnContactDetails($user);
            }
        }
    }

    /**
     * Saves the email and mobile from the account settings page.
     *
     * Only those two fields are read from the request. Everything else the
     * form could carry - role, active, square_customer_id - is ignored, so
     * this page cannot be used to change anything but contact details.
     *
     * @param \App\Model\Entity\User $user
     * @return \Cake\Http\Response|null
     */
    protected function updateOwnContactDetails(\App\Model\Entity\User $user)
    {
        $newEmail = trim((string)$this->request->getData('email'));
        $newPhone = PhoneNumber::normalise((string)$this->request->getData('phone'))
            ?? trim((string)$this->request->getData('phone'));

        $emailChanged = $newEmail !== '' && $newEmail !== $user->email;
        $phoneChanged = $newPhone !== '' && $newPhone !== $user->phone;

        if (!$emailChanged && !$phoneChanged) {
            $this->Flash->success('Your details are already up to date.');

            return $this->redirect(['action' => 'accountSettings']);
        }

        /*
         * Authorisation, as distinct from the verification below. The code
         * sent to the new address only proves that address is reachable; it
         * says nothing about whether the account's owner wanted the change.
         * Asking for the current password is what stops someone who finds an
         * unlocked session from redirecting the account to themselves.
         */
        $hasher = new \Authentication\PasswordHasher\DefaultPasswordHasher();

        if (!$hasher->check((string)$this->request->getData('current_password'), $user->password)) {
            $this->Flash->error('Enter your current password to change your contact details.');

            $this->set('user', $user);
            $this->set('identity', $this->request->getAttribute('identity'));
            $this->set('contactErrors', [
                'current_password' => ['That password does not match the one on your account.'],
            ]);

            return null;
        }

        /*
         * Check the new values against the same rules a save would apply -
         * format, and the unique indexes - before mailing a code to them.
         * The entity is a throwaway copy so the account itself is untouched
         * whatever the outcome.
         */
        $candidate = $this->Users->patchEntity(
            $this->Users->get($user->id),
            array_filter([
                'email' => $emailChanged ? $newEmail : null,
                'phone' => $phoneChanged ? $newPhone : null,
            ], fn ($value) => $value !== null)
        );

        $errors = $candidate->getErrors();

        if (!$errors) {
            // Uniqueness lives in the rules layer, not validation.
            $this->Users->checkRules($candidate, \Cake\Datasource\RulesChecker::UPDATE);
            $errors = $candidate->getErrors();
        }

        if ($errors) {
            $this->Flash->error('Your details could not be saved. Please correct the errors below.');

            $this->set('user', $candidate);
            $this->set('identity', $this->request->getAttribute('identity'));

            return null;
        }

        $verification = new ContactVerificationService();
        $sent = [];
        $failed = [];

        foreach ([
            [VerificationCodesTable::CHANNEL_EMAIL, $emailChanged, $newEmail, 'email address'],
            [VerificationCodesTable::CHANNEL_SMS, $phoneChanged, $newPhone, 'mobile number'],
        ] as [$channel, $changed, $destination, $label]) {
            if (!$changed) {
                continue;
            }

            if ($verification->startVerification($user, $channel, $destination)) {
                $sent[] = $label;
            } else {
                $failed[] = $label;
            }
        }

        if ($sent) {
            $this->Flash->success(
                'We have sent a verification code to your new ' . implode(' and ', $sent)
                . '. Enter it below to confirm the change.'
            );
        }

        if ($failed) {
            $this->Flash->error(
                'We could not send a code to your new ' . implode(' and ', $failed)
                . '. Please check it and try again.'
            );
        }

        return $this->redirect(['action' => 'accountSettings']);
    }

    /**
     * Checks a verification code and applies the pending contact change.
     *
     * @param \App\Model\Entity\User $user
     * @return \Cake\Http\Response|null
     */
    protected function confirmContactChange(\App\Model\Entity\User $user)
    {
        $channel = (string)$this->request->getData('channel');

        if (!in_array($channel, [
            VerificationCodesTable::CHANNEL_EMAIL,
            VerificationCodesTable::CHANNEL_SMS,
        ], true)) {
            $this->Flash->error('Unknown verification request.');

            return $this->redirect(['action' => 'accountSettings']);
        }

        $field = $channel === VerificationCodesTable::CHANNEL_SMS ? 'phone' : 'email';

        // Captured before the change lands, since it is what gets restored.
        $previousValue = (string)$user->{$field};
        $previousEmail = (string)$user->email;

        $result = (new ContactVerificationService())->confirm(
            $user,
            $channel,
            (string)$this->request->getData('code')
        );

        if (!$result['ok']) {
            $this->Flash->error($result['message']);

            return $this->redirect(['action' => 'accountSettings']);
        }

        /*
         * Warn the address that was just replaced, and give it a way back.
         * A phone change is announced by email too - there is no SMS
         * provider, and the old number may be one the owner has lost.
         */
        (new ContactChangeRevertService())->recordAndNotify(
            $user,
            $field,
            $previousValue,
            (string)$user->{$field},
            $previousEmail
        );

        /*
         * A confirmed number or address may now match a Square customer this
         * account could not be tied to before.
         */
        try {
            (new CustomerLinkService())->linkUser($user);
        } catch (\Throwable $e) {
            \Cake\Log\Log::error(
                'Could not link account after a contact change: ' . $e->getMessage()
            );
        }

        /*
         * The identity is a snapshot taken at login, so the stored copy has
         * to be refreshed or the header keeps showing the old address until
         * the next sign in.
         *
         * Written straight to the session authenticator's key rather than
         * through Authentication::setIdentity(), because that clears the
         * identity first and clearing renews the session id. Editing your own
         * email is not a privilege change, and rotating the session on every
         * save logs out any request already in flight.
         */
        $this->request->getSession()->write(self::IDENTITY_SESSION_KEY, $user);

        $this->Flash->success($result['message']);

        return $this->redirect(['action' => 'accountSettings']);
    }

    /**
     * Throws away a pending contact change.
     *
     * @param \App\Model\Entity\User $user
     * @return \Cake\Http\Response|null
     */
    protected function cancelContactChange(\App\Model\Entity\User $user)
    {
        $channel = (string)$this->request->getData('channel');

        (new ContactVerificationService())->cancel($user, $channel);

        $this->Flash->success('That change has been discarded.');

        return $this->redirect(['action' => 'accountSettings']);
    }

    /**
     * Saves a new password from the account settings page.
     *
     * @param \App\Model\Entity\User $user
     * @return \Cake\Http\Response|null
     */
    protected function updateOwnPassword(\App\Model\Entity\User $user)
    {
        /*
         * Proving the current password stops someone who finds an unlocked
         * device from taking the account over: without it they could set a
         * new password and lock the owner out.
         */
        $currentPassword = (string)$this->request->getData('current_password');
        $hasher = new \Authentication\PasswordHasher\DefaultPasswordHasher();

        if (!$hasher->check($currentPassword, $user->password)) {
            $this->Flash->error('Your current password is not correct.');

            $this->set('user', $user);
            $this->set('identity', $this->request->getAttribute('identity'));
            $this->set('passwordErrors', [
                'current_password' => ['That password does not match the one on your account.'],
            ]);

            return null;
        }

        $user = $this->Users->patchEntity(
            $user,
            [
                'password' => $this->request->getData('password'),
                'password_confirm' => $this->request->getData('password_confirm'),
            ],
            ['validate' => 'changePassword']
        );

        if ($this->Users->save($user)) {
            $this->Flash->success('Your password has been updated.');

            return $this->redirect(['action' => 'accountSettings']);
        }

        $this->Flash->error('Your password could not be updated. Please correct the errors below.');

        $this->set('user', $user);
        $this->set('identity', $this->request->getAttribute('identity'));
        $this->set('passwordErrors', $user->getErrors());

        return null;
    }

    /**
     * Emails the signed-in user a reset link for their own account.
     *
     * The alternative to the inline form for anyone who would rather change
     * their password from their inbox than type the current one.
     *
     * @param \App\Model\Entity\User $user
     * @return \Cake\Http\Response|null
     */
    protected function emailOwnPasswordReset(\App\Model\Entity\User $user)
    {
        if ($this->sendPasswordResetEmail($user)) {
            $this->Flash->success(
                'We have sent a password reset link to ' . $user->email
                . '. It expires in one hour.'
            );
        } else {
            $this->Flash->error('We could not send the reset email. Please try again.');
        }

        return $this->redirect(['action' => 'accountSettings']);
    }

    /**
     * Undoes a contact change from the link in the warning email.
     *
     * Public: whoever needs this may well be locked out of the account, so
     * requiring a login would defeat the purpose. The token in the link is
     * the authorisation.
     *
     * GET only describes what will happen; the change is made on POST, so a
     * mail client or link scanner following the URL cannot trigger it.
     *
     * @param string|null $token
     * @return \Cake\Http\Response|null|void
     */
    public function revertContactChange(?string $token = null)
    {
        $this->viewBuilder()->setLayout('account');

        $revertsTable = $this->fetchTable('ContactChangeReverts');
        $revert = $revertsTable->findByToken((string)$token);

        if (!$revert) {
            $this->Flash->error('This link is invalid, has expired, or has already been used.');

            return $this->redirect(['action' => 'login']);
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $service = new ContactChangeRevertService();
            $result = $service->revert($revert);

            if (!$result['ok']) {
                $this->Flash->error($result['message']);

                return $this->redirect(['action' => 'login']);
            }

            /*
             * Whoever made the change knew the account password, so putting
             * the address back is not enough on its own - they could simply
             * repeat it. A reset link to the restored address lets the owner
             * shut that door.
             */
            $user = $this->Users->get($revert->user_id);
            $passwordEmailSent = $this->sendPasswordResetEmail($user);

            $this->Flash->success(
                $result['message']
                . ($passwordEmailSent
                    ? ' We have also emailed you a link to set a new password.'
                    : ' Please use "Forgot password?" to set a new password.')
            );

            return $this->redirect(['action' => 'login']);
        }

        $this->set(compact('revert', 'token'));
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

        /*
         * Which account email goes out from is set by the developers in
         * .env, not here - it is one-off technical setup the studio should
         * never have to touch. What the studio does edit is the wording.
         */
        $this->set('emailFields', \App\Model\Table\EmailContentsTable::FIELDS);
        $this->set('emailValues', $this->fetchTable('EmailContents')->values());
        $this->set('emailPlaceholders', \App\Model\Table\EmailContentsTable::PLACEHOLDERS);
    }

    /**
     * Saves the rebooking reminder's wording, or restores the original.
     *
     * @return \Cake\Http\Response|null
     */
    public function saveEmailContent()
    {
        $this->requireAdmin();
        $this->request->allowMethod(['post']);

        $table = $this->fetchTable('EmailContents');

        if ($this->request->getData('reset')) {
            $table->resetToDefaults();
            $this->Flash->success('The reminder email is back to its original wording.', ['key' => 'email']);
        } else {
            $table->saveValues((array)$this->request->getData('email'));
            $this->Flash->success('Reminder email saved.', ['key' => 'email']);
        }

        return $this->redirect(['action' => 'settings', '#' => 'email-content']);
    }
}
