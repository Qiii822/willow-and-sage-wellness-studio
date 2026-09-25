<?php
/**
 * Customer-facing account settings
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var \Cake\Datasource\EntityInterface|null $pendingEmail
 * @var \Cake\Datasource\EntityInterface|null $pendingSms
 * @var bool $smsIsSimulated
 */

use App\Model\Table\UsersTable;
use App\Service\ContactVerificationService;
use App\Utility\PhoneNumber;

$this->assign('title', 'Account Settings');

$this->Html->css('account', ['block' => true]);
$this->Html->css('password_toggle', ['block' => true]);

$this->Html->script('password_toggle', ['block' => true]);
$this->Html->script('submit_guard', ['block' => true]);
?>

<?= $this->element('site_header') ?>

<main class="account-main">

    <div class="account-inner">

        <?= $this->Flash->render() ?>

        <header class="account-card account-intro">
            <p class="account-eyebrow">Account</p>
            <h1>Settings</h1>
            <p class="account-lead">
                Manage the details we use to reach you, and your password.
            </p>
        </header>

        <section class="account-card">

            <h2>Contact details</h2>
            <p class="account-card-lead">
                Your mobile is what ties a Square booking back to this
                account, so keep it current. A new address or number is
                confirmed with a code before it replaces the old one.
            </p>

            <?php
            /*
             * Any change waiting on a code is shown first: until it is
             * entered the account still holds the previous value, so the
             * fields below deliberately keep showing the old one.
             */
            $pending = [];

            if (!empty($pendingEmail)) {
                $pending[] = [
                    'channel' => 'email',
                    'label' => 'email address',
                    'destination' => $pendingEmail->destination,
                    'note' => null,
                ];
            }

            if (!empty($pendingSms)) {
                $pending[] = [
                    'channel' => 'sms',
                    'label' => 'mobile number',
                    'destination' => PhoneNumber::format($pendingSms->destination),
                    'note' => !empty($smsIsSimulated)
                        ? 'No SMS provider is connected yet, so this code was written to the application log rather than texted.'
                        : null,
                ];
            }
            ?>

            <?php foreach ($pending as $item): ?>

                <div class="account-pending">

                    <p class="account-pending-lead">
                        Confirm your new <?= h($item['label']) ?>
                        <strong><?= h($item['destination']) ?></strong>
                        by entering the <?= h(\App\Service\ContactVerificationService::CODE_LENGTH) ?>-digit
                        code we sent to it.
                    </p>

                    <?php if ($item['note']): ?>
                        <p class="account-pending-note"><?= h($item['note']) ?></p>
                    <?php endif; ?>

                    <?= $this->Form->create(null, [
                        'class' => 'account-form account-code-form',
                        'url' => ['action' => 'accountSettings'],
                        'data-guard' => '1',
                    ]) ?>

                    <?= $this->Form->hidden('form', ['value' => 'verify_code']) ?>
                    <?= $this->Form->hidden('channel', ['value' => $item['channel']]) ?>

                    <div class="account-code-row">
                        <?= $this->Form->text('code', [
                            'class' => 'account-input account-code-input',
                            'required' => true,
                            'inputmode' => 'numeric',
                            'autocomplete' => 'one-time-code',
                            'pattern' => '[0-9]*',
                            'maxlength' => \App\Service\ContactVerificationService::CODE_LENGTH,
                            'placeholder' => str_repeat('0', \App\Service\ContactVerificationService::CODE_LENGTH),
                            'aria-label' => 'Verification code',
                        ]) ?>

                        <?= $this->Form->button('Confirm', [
                            'class' => 'account-button',
                            'data-busy-label' => 'Checking…',
                        ]) ?>
                    </div>

                    <?= $this->Form->end() ?>

                    <?= $this->Form->create(null, [
                        'class' => 'account-form',
                        'url' => ['action' => 'accountSettings'],
                    ]) ?>
                    <?= $this->Form->hidden('form', ['value' => 'cancel_verification']) ?>
                    <?= $this->Form->hidden('channel', ['value' => $item['channel']]) ?>
                    <?= $this->Form->button('Discard this change', [
                        'class' => 'account-link-button',
                    ]) ?>
                    <?= $this->Form->end() ?>

                </div>

            <?php endforeach; ?>

            <?= $this->Form->create($user, [
                'class' => 'account-form',
                'url' => ['action' => 'accountSettings'],
                'data-guard' => '1',
            ]) ?>

            <?= $this->Form->hidden('form', ['value' => 'contact']) ?>

            <div class="account-field">
                <?= $this->Form->control('email', [
                    'type' => 'email',
                    'label' => 'Email address',
                    'required' => true,
                    'class' => 'account-input',
                    'maxlength' => UsersTable::EMAIL_MAX,
                    'autocomplete' => 'email',
                ]) ?>
            </div>

            <div class="account-field">
                <?= $this->Form->control('phone', [
                    'type' => 'tel',
                    'label' => 'Mobile number',
                    'required' => true,
                    'class' => 'account-input',
                    'placeholder' => '0412 345 678',
                    'maxlength' => 20,
                    'autocomplete' => 'tel',
                    'inputmode' => 'tel',
                    'value' => PhoneNumber::format($user->phone),
                ]) ?>

                <p class="account-hint">
                    Australian mobile. Use this same number when you book.
                </p>
            </div>

            <div class="account-field">
                <label for="contact-current-password">Current password</label>

                <?= $this->Form->password('current_password', [
                    'id' => 'contact-current-password',
                    'required' => true,
                    'value' => '',
                    'class' => 'account-input',
                    'placeholder' => 'Confirm it is you',
                    'autocomplete' => 'current-password',
                    'data-toggle' => '1',
                ]) ?>

                <?php if (!empty($contactErrors['current_password'])): ?>
                    <div class="account-error">
                        <?php foreach ((array)$contactErrors['current_password'] as $message): ?>
                            <p><?= h($message) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="account-hint">
                    Required to change your contact details. We will also
                    email your old address so an unexpected change can be
                    undone.
                </p>
            </div>

            <?= $this->Form->button('Save details', [
                'class' => 'account-button',
                'data-busy-label' => 'Saving…',
            ]) ?>

            <?= $this->Form->end() ?>

        </section>

        <section class="account-card">

            <h2>Change password</h2>
            <p class="account-card-lead">
                Update the password you use to sign in.
            </p>

            <?= $this->Form->create(null, [
                'class' => 'account-form',
                'url' => ['action' => 'accountSettings'],
                'data-guard' => '1',
            ]) ?>

            <?= $this->Form->hidden('form', ['value' => 'password']) ?>

            <div class="account-field">
                <label for="current-password">Current password</label>

                <?= $this->Form->password('current_password', [
                    'id' => 'current-password',
                    'required' => true,
                    'value' => '',
                    'class' => 'account-input',
                    'placeholder' => 'Your password right now',
                    'autocomplete' => 'current-password',
                    'data-toggle' => '1',
                ]) ?>

                <?php if (!empty($passwordErrors['current_password'])): ?>
                    <div class="account-error">
                        <?php foreach ((array)$passwordErrors['current_password'] as $message): ?>
                            <p><?= h($message) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="account-field">
                <label for="new-password">New password</label>

                <?= $this->Form->password('password', [
                    'id' => 'new-password',
                    'required' => true,
                    'value' => '',
                    'class' => 'account-input',
                    'placeholder' => UsersTable::PASSWORD_MIN . '-' . UsersTable::PASSWORD_MAX . ' characters',
                    'minlength' => UsersTable::PASSWORD_MIN,
                    'maxlength' => UsersTable::PASSWORD_MAX,
                    'autocomplete' => 'new-password',
                    'data-toggle' => '1',
                ]) ?>

                <?php if (!empty($passwordErrors['password'])): ?>
                    <div class="account-error">
                        <?php foreach ((array)$passwordErrors['password'] as $message): ?>
                            <p><?= h($message) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="account-hint">
                    <?= UsersTable::PASSWORD_MIN ?>&ndash;<?= UsersTable::PASSWORD_MAX ?>
                    characters, with an uppercase letter, a lowercase letter
                    and a number.
                </p>
            </div>

            <div class="account-field">
                <label for="confirm-password">Confirm new password</label>

                <?= $this->Form->password('password_confirm', [
                    'id' => 'confirm-password',
                    'required' => true,
                    'value' => '',
                    'class' => 'account-input',
                    'placeholder' => 'Re-enter your new password',
                    'minlength' => UsersTable::PASSWORD_MIN,
                    'maxlength' => UsersTable::PASSWORD_MAX,
                    'autocomplete' => 'new-password',
                    'data-toggle' => '1',
                ]) ?>

                <?php if (!empty($passwordErrors['password_confirm'])): ?>
                    <div class="account-error">
                        <?php foreach ((array)$passwordErrors['password_confirm'] as $message): ?>
                            <p><?= h($message) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?= $this->Form->button('Update password', [
                'class' => 'account-button',
                'data-busy-label' => 'Updating…',
            ]) ?>

            <?= $this->Form->end() ?>

            <?php /*
             * The way out for someone who cannot remember the current
             * password: a one-hour link to the address on the account.
             */ ?>
            <div class="account-alt">

                <p class="account-alt-lead">
                    Forgotten your current password? We can email a reset
                    link to <strong><?= h($user->email) ?></strong> instead.
                </p>

                <?= $this->Form->create(null, [
                    'class' => 'account-form',
                    'url' => ['action' => 'accountSettings'],
                    'data-guard' => '1',
                ]) ?>

                <?= $this->Form->hidden('form', ['value' => 'password_email']) ?>

                <?= $this->Form->button('Email me a reset link', [
                    'class' => 'account-button is-secondary',
                    'data-busy-label' => 'Sending…',
                ]) ?>

                <?= $this->Form->end() ?>

            </div>

        </section>

    </div>

</main>

<?= $this->element('site_footer') ?>
