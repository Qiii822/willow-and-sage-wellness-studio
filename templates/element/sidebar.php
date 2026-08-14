<aside class="sidebar">
    <div class="logo">
        <div class="logo-icon">W</div>

        <div>
            <h2>Wendy</h2>
            <span>Admin Dashboard</span>
        </div>
    </div>
    <ul class="sidebar-nav">
        <li class="active">
            <i data-lucide="layout-dashboard"></i>
            Dashboard
        </li>



        <li>
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
