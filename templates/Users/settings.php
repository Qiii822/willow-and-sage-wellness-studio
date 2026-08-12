<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface|null $squareIdentity
 * @var \Cake\I18n\DateTime|null $lastSyncedAt
 * @var bool $justConnected
 */
?>
<div class="users settings content">
    <h3>Settings</h3>

    <?php
        $identity = $this->request->getAttribute('identity');
        $role = $identity->roles ?? $identity->role ?? null;
        $isSuperAdmin = $role === 'superadmin';
    ?>

    <h4>Square connection</h4>

    <div style="margin-bottom: 1.5rem; padding: 1rem; border: 1px solid #ddd; border-radius: 4px;">
        <?php if ($squareIdentity): ?>
            <p style="margin-bottom: 0.25rem;">
                <strong>Square connected</strong>
                <?php if ($isSuperAdmin): ?>
                    (merchant <?= h($squareIdentity->merchant_id) ?>)
                <?php endif; ?>
            </p>
            <p style="color: #888; margin-bottom: 0.75rem;">
                <?= $lastSyncedAt ? 'Last synced ' . h($lastSyncedAt->timeAgoInWords()) : 'Not yet synced' ?>
            </p>

            <?php if ($isSuperAdmin): ?>
                <?= $this->Form->create(null, [
                    'url' => ['controller' => 'Auth', 'action' => 'sync'],
                    'style' => 'display: inline-block; margin-right: 0.5rem;',
                ]) ?>
                    <?= $this->Form->button('Sync now', ['class' => 'button']) ?>
                <?= $this->Form->end() ?>

                <?= $this->Form->create(null, [
                    'url' => ['controller' => 'Auth', 'action' => 'disconnect'],
                    'style' => 'display: inline-block;',
                ]) ?>
                    <?= $this->Form->hidden('revoke_square', ['value' => 1]) ?>
                    <?= $this->Form->button('Disconnect Square', [
                        'class' => 'button button-outline',
                        'onclick' => "return confirm('Disconnect Square? This will revoke the app\'s access.');",
                    ]) ?>
                <?= $this->Form->end() ?>

                <?php if ($justConnected): ?>
                    <div style="margin-top: 1rem; padding: 0.75rem; background: #eef6ff; border-radius: 4px;">
                        <p style="margin-bottom: 0.5rem;">Square connected! Would you like to sync your customers now?</p>
                        <?= $this->Form->create(null, [
                            'url' => ['controller' => 'Auth', 'action' => 'sync'],
                            'style' => 'display: inline-block; margin-right: 0.5rem;',
                        ]) ?>
                            <?= $this->Form->button('Sync now', ['class' => 'button']) ?>
                        <?= $this->Form->end() ?>
                        <?= $this->Html->link('Not now', ['controller' => 'Users', 'action' => 'settings']) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>
            <p style="margin-bottom: 0.75rem;">Square is not connected.</p>

            <?php if ($lastSyncedAt): ?>
                <p style="color: #b45309; margin-bottom: 0.75rem;">
                    Customer data may be stale - last synced <?= h($lastSyncedAt->timeAgoInWords()) ?>, before Square was disconnected.
                </p>
            <?php endif; ?>

            <?php if ($isSuperAdmin): ?>
                <?= $this->Html->link(
                    'Connect Square',
                    ['controller' => 'Auth', 'action' => 'beginAuth'],
                    ['class' => 'button']
                ) ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?= $this->Html->link(__('Back to profile'), ['controller' => 'Users', 'action' => 'profile']) ?>
</div>