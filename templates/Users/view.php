<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */

$this->assign('title', 'View User');

$this->Html->css(
    'users',
    ['block' => true]
);
?>

<div class="users-page">

    <div class="users-header">
        <div>
            <h1>View User</h1>
            <p>View local user account details.</p>
        </div>
    </div>

    <div class="users-card">

        <table class="users-table">

            <tbody>

            <tr>
                <th>Name</th>
                <td>
                    <?= h(trim(
                        ($user->first_name ?? '') . ' ' .
                        ($user->surname ?? '')
                    )) ?>
                </td>
            </tr>

            <tr>
                <th>Email</th>
                <td>
                    <?= h($user->email ?? '') ?>
                </td>
            </tr>

            <tr>
                <th>Role</th>
                <td>
                    <span class="user-role">
                        <?= h($user->roles ?? '') ?>
                    </span>
                </td>
            </tr>

            <tr>
                <th>Status</th>
                <td>
                    <?php if (!empty($user->active)): ?>

                        <span class="user-status active">
                            <span class="user-status-dot"></span>
                            Active
                        </span>

                    <?php else: ?>

                        <span class="user-status inactive">
                            <span class="user-status-dot"></span>
                            Inactive
                        </span>

                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th>Created</th>
                <td>
                    <?php if ($user->created_at): ?>
                        <?= h($user->created_at->format('d/m/Y H:i')) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>

            </tbody>

        </table>

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
