<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $users
 * @var string $search
 * @var string $role
 * @var string $status
 * @var string $createdFrom
 * @var string $createdTo
 * @var bool $hasFilters
 */

$this->assign('title', 'Users');

$this->Html->css(
    'users',
    ['block' => true]
);

$identity = $this->request->getAttribute('identity');

$currentUserId = $identity->id ?? null;

$currentRole = $identity->roles ?? $identity->role ?? null;
?>

<div class="users-page">

    <div class="users-header">

        <div>
            <h1>Users</h1>

            <p>
                Manage local users and administrator accounts.
            </p>
        </div>

        <?= $this->Html->link(
            'Create User',
            [
                'controller' => 'Users',
                'action' => 'add'
            ],
            [
                'class' => 'user-create-button'
            ]
        ) ?>

    </div>

    <form
        class="users-search"
        method="get"
        action="<?= $this->Url->build([
            'controller' => 'Users',
            'action' => 'index'
        ]) ?>"
    >

        <div class="users-search-field">

            <i data-lucide="search"></i>

            <input
                type="search"
                name="q"
                value="<?= h($search ?? '') ?>"
                placeholder="Search by name or email"
                aria-label="Search users by name or email"
            >

        </div>

        <select
            name="role"
            class="users-filter-select"
            aria-label="Filter by role"
        >
            <option value="">All roles</option>

            <?php foreach ([
                'user' => 'User',
                'admin' => 'Admin',
                'superadmin' => 'Superadmin',
            ] as $value => $label): ?>

                <option
                    value="<?= h($value) ?>"
                    <?= ($role ?? '') === $value ? 'selected' : '' ?>
                >
                    <?= h($label) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <select
            name="status"
            class="users-filter-select"
            aria-label="Filter by status"
        >
            <option value="">All statuses</option>

            <option
                value="active"
                <?= ($status ?? '') === 'active' ? 'selected' : '' ?>
            >
                Active
            </option>

            <option
                value="inactive"
                <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>
            >
                Inactive
            </option>

        </select>

        <div class="users-date-range">

            <span class="users-date-label">Created</span>

            <input
                type="date"
                name="created_from"
                value="<?= h($createdFrom ?? '') ?>"
                aria-label="Created on or after"
            >

            <span class="users-date-sep">&ndash;</span>

            <input
                type="date"
                name="created_to"
                value="<?= h($createdTo ?? '') ?>"
                aria-label="Created on or before"
            >

        </div>

        <button type="submit" class="users-search-button">
            Apply
        </button>

        <?php if (!empty($hasFilters)): ?>

            <?= $this->Html->link(
                'Clear',
                [
                    'controller' => 'Users',
                    'action' => 'index'
                ],
                [
                    'class' => 'users-search-clear'
                ]
            ) ?>

        <?php endif; ?>

    </form>

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
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>

                <?php $rowCount = 0; ?>

                <?php foreach ($users as $user): ?>

                    <?php $rowCount++; ?>

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

                        <td>
                            <div class="user-actions">

                                <?= $this->Html->link(
                                    'View',
                                    [
                                        'controller' => 'Users',
                                        'action' => 'view',
                                        $user->id
                                    ],
                                    [
                                        'class' => 'user-action-link'
                                    ]
                                ) ?>

                                <?php
                                $targetRole =
                                    $user->roles
                                    ?? $user->role
                                    ?? null;

                                $canEdit =
                                    $currentRole === 'superadmin'
                                    ||
                                    (
                                        $currentRole === 'admin'
                                        &&
                                        (
                                            $targetRole === 'user'
                                            ||
                                            (string)$currentUserId === (string)$user->id
                                        )
                                    );
                                ?>

                                <?php if ($canEdit): ?>

                                    <?= $this->Html->link(
                                        'Edit',
                                        [
                                            'controller' => 'Users',
                                            'action' => 'edit',
                                            $user->id
                                        ],
                                        [
                                            'class' => 'user-action-link'
                                        ]
                                    ) ?>

                                <?php endif; ?>

                            </div>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if ($rowCount === 0): ?>

                    <tr>
                        <td colspan="6" class="users-empty">
                            <?php if (!empty($hasFilters)): ?>
                                No users match the current search and filters.
                            <?php else: ?>
                                No users yet.
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>
