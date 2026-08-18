<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $users
 */

$this->assign('title', 'Users');

$this->Html->css(
    'users',
    ['block' => true]
);
?>

<div class="users-page">

    <div class="users-header">

        <div>
            <h1>Users</h1>

            <p>
                Manage local users and administrator accounts.
            </p>
        </div>

    </div>

    <div class="users-card">

        <div class="table-responsive">

            <table class="users-table">

                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
                </thead>

                <tbody>

                <?php foreach ($users as $user): ?>

                    <tr>

                        <td>
                            <?= h(trim(
                                ($user->first_name ?? '') . ' ' .
                                ($user->surname ?? '')
                            )) ?>
                        </td>

                        <td>
                            <?= h($user->email ?? '') ?>
                        </td>

                        <td>
                            <span class="user-role">
                                <?= h($user->roles ?? '') ?>
                            </span>
                        </td>

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

                        <td>
                            <?php if ($user->created_at): ?>

                                <?= h(
                                    $user->created_at
                                        ->format('d/m/Y')
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

    <?= $this->Html->link(
        '← Back to Dashboard',
        [
            'controller' => 'Dashboard',
            'action' => 'index',
        ],
        [
            'class' => 'users-back'
        ]
    ) ?>

</div>
