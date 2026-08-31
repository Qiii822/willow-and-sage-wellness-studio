<?php
$controller = $this->request->getParam('controller');
$action = $this->request->getParam('action');

$identity = $this->request->getAttribute('identity');
$role = $identity->roles ?? $identity->role ?? null;

$isDashboard =
    $controller === 'Dashboard' &&
    $action === 'index';

$isUsers =
    $controller === 'Users' &&
    $action === 'index';

$isBookings =
    $controller === 'Bookings' &&
    $action === 'index';

$isPayments =
    $controller === 'Payments' &&
    $action === 'index';

$isSettings =
    $controller === 'Users' &&
    $action === 'settings';

$isSiteContent = $controller === 'SiteContents';

$isServices = $controller === 'Services';
?>

<aside class="sidebar">

    <div class="logo">
        <?= $this->Html->link(
            '<div class="logo-icon">W</div>
        <div class="logo-text">
            <h2>' . h($identity->first_name ?? '') . '</h2>
        </div>',
            [
                'controller' => 'Users',
                'action' => 'profile'
            ],
            [
                'escape' => false,
                'class' => 'logo-link'
            ]
        ) ?>
    </div>

    <ul class="sidebar-nav">

        <li class="<?= $isDashboard ? 'active' : '' ?>">
            <?= $this->Html->link(
                '<i data-lucide="layout-dashboard"></i><span>Dashboard</span>',
                [
                    'controller' => 'Dashboard',
                    'action' => 'index'
                ],
                [
                    'escape' => false,
                    'class' => 'sidebar-link'
                ]
            ) ?>
        </li>

        <li class="<?= $isBookings ? 'active' : '' ?>">
            <?= $this->Html->link(
                '<i data-lucide="calendar-check"></i><span>Bookings</span>',
                [
                    'controller' => 'Bookings',
                    'action' => 'index'
                ],
                [
                    'escape' => false,
                    'class' => 'sidebar-link'
                ]
            ) ?>
        </li>

        <?php if (in_array(
            $role,
            ['admin', 'superadmin'],
            true
        )): ?>

            <li class="<?= $isPayments ? 'active' : '' ?>">
                <?= $this->Html->link(
                    '<i data-lucide="credit-card"></i><span>Payments</span>',
                    [
                        'controller' => 'Payments',
                        'action' => 'index'
                    ],
                    [
                        'escape' => false,
                        'class' => 'sidebar-link'
                    ]
                ) ?>
            </li>

        <?php endif; ?>


        <?php if (in_array(
            $role,
            ['admin', 'superadmin'],
            true
        )): ?>

            <li class="<?= $isUsers ? 'active' : '' ?>">
                <?= $this->Html->link(
                    '<i data-lucide="users"></i><span>Users</span>',
                    [
                        'controller' => 'Users',
                        'action' => 'index'
                    ],
                    [
                        'escape' => false,
                        'class' => 'sidebar-link'
                    ]
                ) ?>
            </li>

        <?php endif; ?>


        <?php if (in_array(
            $role,
            ['admin', 'superadmin'],
            true
        )): ?>

            <li class="<?= $isServices ? 'active' : '' ?>">
                <?= $this->Html->link(
                    '<i data-lucide="list"></i><span>Services</span>',
                    [
                        'controller' => 'Services',
                        'action' => 'manage'
                    ],
                    [
                        'escape' => false,
                        'class' => 'sidebar-link'
                    ]
                ) ?>
            </li>

            <li class="<?= $isSiteContent ? 'active' : '' ?>">
                <?= $this->Html->link(
                    '<i data-lucide="pencil"></i><span>Website content</span>',
                    [
                        'controller' => 'SiteContents',
                        'action' => 'index'
                    ],
                    [
                        'escape' => false,
                        'class' => 'sidebar-link'
                    ]
                ) ?>
            </li>

        <?php endif; ?>


        <?php if (in_array(
            $role,
            ['admin', 'superadmin'],
            true
        )): ?>

            <li class="<?= $isSettings ? 'active' : '' ?>">
                <?= $this->Html->link(
                    '<i data-lucide="settings"></i><span>Settings</span>',
                    [
                        'controller' => 'Users',
                        'action' => 'settings'
                    ],
                    [
                        'escape' => false,
                        'class' => 'sidebar-link'
                    ]
                ) ?>
            </li>

        <?php endif; ?>
        
    </ul>

    <div class="logout-section">
        <?= $this->Form->create(null, [
            'url' => [
                'controller' => 'Users',
                'action' => 'logout'
            ]
        ]) ?>

        <?= $this->Form->button('Logout') ?>

        <?= $this->Form->end() ?>
    </div>

</aside>
