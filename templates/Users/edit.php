<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */

$this->assign('title', 'Edit User');

$this->Html->css(
    'users',
    ['block' => true]
);
$this->Html->css('confirm_modal', ['block' => true]);

$identity = $this->request->getAttribute('identity');
$currentRole = $identity->roles ?? $identity->role ?? null;
$isSuperAdmin = $currentRole === 'superadmin';
$isEditingSelf = $identity->id === $user->id;
?>

<div class="users-page">

    <div class="users-header">
        <div>
            <h1>Edit User</h1>
            <p>Update local user account details.</p>
        </div>
    </div>

    <div class="users-card">

        <?= $this->Form->create($user, [
            'id' => 'user-edit-form'
        ]) ?>

        <div class="user-form-field">
            <?= $this->Form->control('first_name', [
                'label' => 'First Name',
                'required' => true,
                'maxlength' => 50,
            ]) ?>
            <p class="user-form-hint">Maximum 50 characters</p>
        </div>

        <div class="user-form-field">
            <?= $this->Form->control('surname', [
                'label' => 'Surname',
                'required' => true,
                'maxlength' => 50,
            ]) ?>
            <p class="user-form-hint">Maximum 50 characters</p>
        </div>

        <?= $this->Form->control('email', [
            'type' => 'email',
            'label' => 'Email',
            'required' => true,
        ]) ?>

        <?php if ($isSuperAdmin && !$isEditingSelf): ?>

            <?= $this->Form->control('roles', [
                'type' => 'select',
                'label' => 'Role',
                'options' => [
                    'user' => 'User',
                    'admin' => 'Admin',
                    'superadmin' => 'Superadmin',
                ],
            ]) ?>

        <?php else: ?>

            <div class="user-readonly-field">
                <label>Role</label>

                <div class="user-readonly-value">
                    <?= h(ucfirst($user->roles ?? '')) ?>
                </div>
            </div>

        <?php endif; ?>

        <div class="user-active-field">

            <?= $this->Form->control('active', [
                'type' => 'checkbox',
                'label' => 'Active',
            ]) ?>

        </div>

        <div class="user-actions-row">

            <div class="user-form-actions">

                <?= $this->Form->button(
                    'Save Changes',
                    [
                        'class' => 'user-form-button'
                    ]
                ) ?>

                <?= $this->Html->link(
                    'Cancel',
                    [
                        'controller' => 'Users',
                        'action' => 'view',
                        $user->id
                    ],
                    [
                        'class' => 'user-form-button secondary'
                    ]
                ) ?>

            </div>

        </div>

        <?= $this->Form->end() ?>

        <?php if ($isSuperAdmin): ?>

            <div class="user-delete-actions">

                <?= $this->Form->postLink(
                    'Delete User',
                    [
                        'controller' => 'Users',
                        'action' => 'delete',
                        $user->id
                    ],
                    [
                        'class' => 'user-form-button danger'
                    ]
                ) ?>

            </div>

        <?php endif; ?>

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

    <div class="confirm-modal-overlay" id="save-confirm-overlay" data-form-id="user-edit-form" hidden>
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="save-confirm-title">
            <h2 id="save-confirm-title">Save changes?</h2>
            <p>Are you sure you want to save these changes?</p>
            <div class="confirm-modal-actions">
                <button type="button" class="user-form-button secondary" id="save-confirm-cancel">Cancel</button>
                <button type="button" class="user-form-button" id="save-confirm-ok">Save Changes</button>
            </div>
        </div>
    </div>

    <div class="confirm-modal-overlay" id="delete-confirm-overlay" data-form-selector=".user-delete-actions form" hidden>
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="delete-confirm-title">
            <h2 id="delete-confirm-title">Delete user?</h2>
            <p>This action cannot be undone. Are you sure you want to delete this user?</p>
            <div class="confirm-modal-actions">
                <button type="button" class="user-form-button secondary" id="delete-confirm-cancel">Cancel</button>
                <button type="button" class="user-form-button danger" id="delete-confirm-ok">Delete User</button>
            </div>
        </div>
    </div>

    <?= $this->Html->script(
        'confirm_modal',
        ['block' => true]
    ) ?>

</div>
