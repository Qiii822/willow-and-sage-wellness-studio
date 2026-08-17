<?php
$this->assign('title', 'Change Password');

$this->Html->css(
    'change_password',
    ['block' => true]
);

$this->Html->script(
    'change_password',
    ['block' => true]
);
?>

<div class="change-password-page">

    <div class="change-password-card">

        <?= $this->Flash->render() ?>

        <div class="change-password-header">
            <h1>Change Password</h1>
            <p>
                Enter and confirm your new password below.
            </p>
        </div>

        <?= $this->Form->create(
            $user,
            [
                'class' => 'change-password-form'
            ]
        ) ?>

        <fieldset>

            <legend>
                <?= __('Please enter a new password') ?>
            </legend>

            <div class="change-password-field">
                <label for="password">New Password</label>

                <div class="password-wrapper">
                    <?= $this->Form->password('password', [
                        'required' => true,
                        'id' => 'password',
                        'class' => 'change-password-input',
                        'placeholder' => 'Enter your new password',
                        'autocomplete' => 'new-password',
                    ]) ?>

                    <button
                        type="button"
                        class="password-toggle"
                        data-password-target="password"
                        aria-label="Show password"
                    >
                        <i data-lucide="eye"></i>
                    </button>
                </div>
            </div>

            <div class="change-password-field">
                <label for="password-confirm">Confirm Password</label>

                <div class="password-wrapper">
                    <?= $this->Form->password('password_confirm', [
                        'required' => true,
                        'id' => 'password-confirm',
                        'class' => 'change-password-input',
                        'placeholder' => 'Confirm your new password',
                        'autocomplete' => 'new-password',
                    ]) ?>

                    <button
                        type="button"
                        class="password-toggle"
                        data-password-target="password-confirm"
                        aria-label="Show password"
                    >
                        <i data-lucide="eye"></i>
                    </button>
                </div>
            </div>

        </fieldset>

        <div class="change-password-actions">

            <?= $this->Form->button(
                __('Save Password'),
                [
                    'class' => 'change-password-button'
                ]
            ) ?>

            <?= $this->Html->link(
                __('Back to Profile'),
                [
                    'controller' => 'Users',
                    'action' => 'profile'
                ],
                [
                    'class' =>
                        'change-password-button secondary'
                ]
            ) ?>

        </div>

        <?= $this->Form->end() ?>

    </div>

</div>
