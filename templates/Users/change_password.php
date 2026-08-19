<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
$this->assign('title', 'Change Password');

$this->Html->css(
    'auth',
    ['block' => true]
);

$this->Html->script(
    'change_password',
    ['block' => true]
);
?>

<div class="auth-page">

    <div class="auth-card">

        <?= $this->Flash->render() ?>

        <div class="auth-header">
            <h1>Change Password</h1>
            <p>
                Enter and confirm your new password below.
            </p>
        </div>

        <?= $this->Form->create(
            $user,
            [
                'class' => 'auth-form'
            ]
        ) ?>

        <fieldset>

            <legend>
                <?= __('Please enter a new password') ?>
            </legend>

            <div class="input password required">
                <label for="password">New Password</label>

                <div class="auth-password-wrapper">

                    <?= $this->Form->password('password', [
                        'required' => true,
                        'id' => 'password',
                        'value' => '',
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Enter your new password'
                    ]) ?>

                    <button
                        type="button"
                        class="auth-password-toggle"
                        data-password-toggle="password"
                        aria-label="Show password"
                    >
                        <i data-lucide="eye"></i>
                    </button>

                </div>

                <?= $this->Form->error('password') ?>
            </div>

            <div class="input password required">
                <label for="password-confirm">Confirm Password</label>

                <div class="auth-password-wrapper">

                    <?= $this->Form->password('password_confirm', [
                        'required' => true,
                        'id' => 'password-confirm',
                        'value' => '',
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Confirm your new password'
                    ]) ?>

                    <button
                        type="button"
                        class="auth-password-toggle"
                        data-password-toggle="password-confirm"
                        aria-label="Show password"
                    >
                        <i data-lucide="eye"></i>
                    </button>

                </div>

                <?= $this->Form->error('password_confirm') ?>
            </div>

        </fieldset>

        <div class="auth-actions">

            <?= $this->Form->button(
                __('Save Password'),
                [
                    'class' => 'auth-button'
                ]
            ) ?>

            <?= $this->Html->link(
                __('Back to Profile'),
                [
                    'controller' => 'Users',
                    'action' => 'profile'
                ],
                [
                    'class' => 'auth-button secondary'
                ]
            ) ?>

        </div>

        <?= $this->Form->end() ?>

    </div>

</div>
