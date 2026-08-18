<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var bool $isSuperAdmin
 */

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

        <?= $this->Form->control('first_name', [
            'label' => 'First Name',
            'required' => true,
        ]) ?>

        <?= $this->Form->control('surname', [
            'label' => 'Surname',
            'required' => true,
        ]) ?>

        <?= $this->Form->control('email', [
            'type' => 'email',
            'label' => 'Email',
            'required' => true,
        ]) ?>

        <div class="user-password-field">

            <label for="password">Password</label>

            <div class="user-password-wrapper">

                <?= $this->Form->password('password', [
                    'id' => 'password',
                    'required' => true,
                    'class' => 'user-password-input',
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

        </div>

        <div class="user-password-field">

            <label for="password-confirm">Confirm Password</label>

            <div class="user-password-wrapper">

                <?= $this->Form->password('password_confirm', [
                    'id' => 'password-confirm',
                    'required' => true,
                    'class' => 'user-password-input',
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
