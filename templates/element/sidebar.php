<?php
$controller = $this->request->getParam('controller');
$action = $this->request->getParam('action');

$isDashboard = $controller === 'Dashboard' && $action === 'index';
$isSettings = $controller === 'Users' && $action === 'settings';
?>
<aside class="sidebar">
    <div class="logo">
        <div class="logo-icon">W</div>

        <div>
            <h2>Wendy</h2>
            <span>Admin Dashboard</span>
        </div>
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
    </ul>

    <div class="logout-section">
        <?= $this->Form->create(null, ['url' => ['controller' => 'Users', 'action' => 'logout']]) ?>
        <?= $this->Form->button('Logout') ?>
        <?= $this->Form->end() ?>
    </div>
</aside>
