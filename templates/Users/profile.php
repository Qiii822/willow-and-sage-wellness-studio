<div class="users profile content">
    <h3>Your Profile</h3>

    <?php
        $displayName = $identity->name ?? trim(($identity->first_name ?? '') . ' ' . ($identity->surname ?? ''));
    ?>

    <table>
        <tr>
            <th><?= __('Name') ?></th>
            <td><?= h($displayName) ?></td>
        </tr>
        <tr>
            <th><?= __('Email') ?></th>
            <td><?= h($identity->email ?? '') ?></td>
        </tr>
        <tr>
            <th><?= __('Role') ?></th>
            <td><?= h($identity->roles ?? $identity->role ?? '') ?></td>
        </tr>
    </table>

    <div class="actions">
        <?php $role = ($identity->roles ?? $identity->role ?? null); ?>
        <?php if (in_array($role, ['admin', 'superadmin'], true)): ?>
            <?= $this->Html->link(__('Settings'), ['controller' => 'Users', 'action' => 'settings'], ['class' => 'button']) ?>
        <?php endif; ?>
        <?= $this->Html->link(__('Reset Password'), ['controller' => 'Users', 'action' => 'changePassword'], ['class' => 'button']) ?>
        <?= $this->Form->postLink(__('Logout'), ['controller' => 'Users', 'action' => 'logout'], ['confirm' => __('Are you sure you want to logout?'), 'class' => 'button']) ?>
    </div>
</div>
