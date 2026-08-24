<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var bool $isSuperAdmin
 */

use App\Model\Table\UsersTable;

$this->assign('title', 'Create User');

$this->Html->css(
    'users',
    ['block' => true]
);
?>

<div class="users-page">

    <div class="users-header">
        <div>
            <h1>Create User</h1>
            <p>Create a new local user account.</p>
        </div>
    </div>

    <div class="users-card">

        <?= $this->Form->create($user) ?>

        <div class="user-form-field">
            <?= $this->Form->control('first_name', [
                'label' => 'First Name',
                'required' => true,
                'maxlength' => UsersTable::NAME_MAX,
            ]) ?>
            <p class="user-form-hint">Maximum <?= UsersTable::NAME_MAX ?> characters</p>
        </div>

        <div class="user-form-field">
            <?= $this->Form->control('surname', [
                'label' => 'Surname',
                'required' => true,
                'maxlength' => UsersTable::NAME_MAX,
            ]) ?>
            <p class="user-form-hint">Maximum <?= UsersTable::NAME_MAX ?> characters</p>
        </div>

        <div class="user-form-field">
            <?= $this->Form->control('email', [
                'type' => 'email',
                'label' => 'Email',
                'required' => true,
                'maxlength' => UsersTable::EMAIL_MAX,
            ]) ?>
            <p class="user-form-hint">Maximum <?= UsersTable::EMAIL_MAX ?> characters</p>
        </div>

        <div class="user-form-field">
            <?= $this->Form->control('phone', [
                'type' => 'tel',
                'label' => 'Mobile Number',
                'required' => true,
                'placeholder' => '0412 345 678',
                'maxlength' => 20,
            ]) ?>
            <p class="user-form-hint">
                Australian mobile. Used to match the account to its Square
                customer record.
            </p>
        </div>

        <div class="user-password-field">

            <label for="password">Password</label>

            <div class="user-password-wrapper">

                <?= $this->Form->password('password', [
                    'id' => 'password',
                    'required' => true,
                    'value' => '',
                    'autocomplete' => 'new-password',
                    'class' => 'user-password-input',
                    'minlength' => UsersTable::PASSWORD_MIN,
                    'maxlength' => UsersTable::PASSWORD_MAX,
                ]) ?>

                <button
                    type="button"
                    class="user-password-toggle"
                    data-target="password"
                    aria-label="Show password"
                >
                    <i data-lucide="eye"></i>
                </button>

            </div>

            <p class="user-form-hint"><?= UsersTable::PASSWORD_MIN ?>&ndash;<?= UsersTable::PASSWORD_MAX ?> characters, including an uppercase letter, a lowercase letter and a number.</p>

            <?= $this->Form->error('password') ?>

        </div>

        <div class="user-password-field">

            <label for="password-confirm">Confirm Password</label>

            <div class="user-password-wrapper">

                <?= $this->Form->password('password_confirm', [
                    'id' => 'password-confirm',
                    'required' => true,
                    'value' => '',
                    'autocomplete' => 'new-password',
                    'class' => 'user-password-input',
                    'minlength' => UsersTable::PASSWORD_MIN,
                    'maxlength' => UsersTable::PASSWORD_MAX,
                ]) ?>

                <button
                    type="button"
                    class="user-password-toggle"
                    data-target="password-confirm"
                    aria-label="Show password"
                >
                    <i data-lucide="eye"></i>
                </button>

            </div>

            <?= $this->Form->error('password_confirm') ?>

        </div>

        <?php if ($isSuperAdmin): ?>

            <?= $this->Form->control('roles', [
                'type' => 'select',
                'label' => 'Role',
                'options' => [
                    'user' => 'User',
                    'admin' => 'Admin',
                    'superadmin' => 'Superadmin',
                ],
                'default' => 'user',
            ]) ?>

        <?php else: ?>

            <div class="user-readonly-field">
                <label>Role</label>

                <div class="user-readonly-value">
                    User
                </div>
            </div>

        <?php endif; ?>

        <div class="user-active-field">

            <?= $this->Form->control('active', [
                'type' => 'checkbox',
                'label' => 'Active',
                'checked' => true,
            ]) ?>

        </div>

        <div class="user-form-actions">

            <?= $this->Form->button(
                'Create User',
                [
                    'class' => 'user-form-button'
                ]
            ) ?>

            <?= $this->Html->link(
                'Cancel',
                [
                    'controller' => 'Users',
                    'action' => 'index'
                ],
                [
                    'class' => 'user-form-button secondary'
                ]
            ) ?>

        </div>

        <?= $this->Form->end() ?>

    </div>

    <?= $this->Html->link(
        '← Back to Users',
        [
            'controller' => 'Users',
            'action' => 'index'
        ],
        [
            'class' => 'users-back'
        ]
    ) ?>

</div>
<?= $this->Html->script(
    'user_password',
    ['block' => true]
) ?>
