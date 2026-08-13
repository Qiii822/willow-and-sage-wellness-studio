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

<!--        <li>-->
<!--            <i data-lucide="users"></i>-->
<!--            Customers-->
<!--        </li>-->
<!---->
<!--        <li>-->
<!--            <i data-lucide="calendar-days"></i>-->
<!--            Bookings-->
<!--        </li>-->
<!---->
<!--        <li>-->
<!--            <i data-lucide="credit-card"></i>-->
<!--            Sales & Payments-->
<!--        </li>-->
<!---->
<!--        <li>-->
<!--            <i data-lucide="message-square"></i>-->
<!--            Feedback-->
<!--        </li>-->
<!---->
<!--        <li>-->
<!--            <i data-lucide="bar-chart-3"></i>-->
<!--            Reports-->
<!--        </li>-->

        <li>
            <i data-lucide="settings"></i>
            Settings
        </li>
    </ul>

    <div class="logout-section">
        <?= $this->Form->create(null, ['url' => ['controller' => 'Users', 'action' => 'logout']]) ?>
        <?= $this->Form->button('Logout') ?>
        <?= $this->Form->end() ?>
    </div>
</aside>
