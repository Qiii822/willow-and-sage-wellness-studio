<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface|null $squareIdentity
 * @var \Cake\I18n\DateTime|null $lastSyncedAt
 * @var bool $justConnected
 */

$this->Html->css('settings', ['block' => true]);

$identity = $this->request->getAttribute('identity');
$role = $identity->roles ?? $identity->role ?? null;
$isSuperAdmin = $role === 'superadmin';
?>

<div class="settings-page">

    <div class="settings-header">
        <h1>Settings</h1>
        <p>Manage your Square connection and customer synchronisation.</p>
    </div>

    <div class="settings-card">

        <div class="settings-card-header">

            <div>
                <h2>Square Connection</h2>
                <p>
                    Manage the Square account connected to Wendy Admin.
                </p>
            </div>

            <?php if ($squareIdentity): ?>
                <div class="connection-status">
                    <span class="connection-dot"></span>
                    Connected
                </div>
            <?php endif; ?>

        </div>

        <?php if ($squareIdentity): ?>

            <div class="connection-details">

                <p>
                    <strong>Status:</strong>
                    Square connected
                </p>

                <?php if ($isSuperAdmin): ?>
                    <p>
                        <strong>Merchant ID:</strong>
                        <?= h($squareIdentity->merchant_id) ?>
                    </p>
                <?php endif; ?>

                <p>
                    <strong>Last synced:</strong>

                    <?= $lastSyncedAt
                        ? h($lastSyncedAt->timeAgoInWords())
                        : 'Not yet synced'
                    ?>
                </p>

            </div>

            <?php if ($isSuperAdmin): ?>

                <div class="settings-actions">

                    <?= $this->Form->create(null, [
                        'url' => [
                            'controller' => 'Auth',
                            'action' => 'sync'
                        ]
                    ]) ?>

                    <?= $this->Form->button(
                        'Sync now',
                        [
                            'class' => 'settings-button'
                        ]
                    ) ?>

                    <?= $this->Form->end() ?>


                    <?= $this->Form->create(null, [
                        'url' => [
                            'controller' => 'Auth',
                            'action' => 'disconnect'
                        ]
                    ]) ?>

                    <?= $this->Form->hidden(
                        'revoke_square',
                        [
                            'value' => 1
                        ]
                    ) ?>

                    <?= $this->Form->button(
                        'Disconnect Square',
                        [
                            'class' => 'settings-button danger',
                            'onclick' =>
                                "return confirm('Disconnect Square? This will revoke the app\\'s access.');"
                        ]
                    ) ?>

                    <?= $this->Form->end() ?>

                </div>


                <?php if ($justConnected): ?>

                    <div class="connection-details" style="margin-top: 20px;">

                        <p>
                            <strong>Square connected successfully.</strong>
                        </p>

                        <p>
                            Would you like to sync your customers now?
                        </p>

                        <div class="settings-actions">

                            <?= $this->Form->create(null, [
                                'url' => [
                                    'controller' => 'Auth',
                                    'action' => 'sync'
                                ]
                            ]) ?>

                            <?= $this->Form->button(
                                'Sync now',
                                [
                                    'class' => 'settings-button'
                                ]
                            ) ?>

                            <?= $this->Form->end() ?>

                            <?= $this->Html->link(
                                'Not now',
                                [
                                    'controller' => 'Users',
                                    'action' => 'settings'
                                ],
                                [
                                    'class' => 'settings-button secondary'
                                ]
                            ) ?>

                        </div>

                    </div>

                <?php endif; ?>

            <?php endif; ?>


        <?php else: ?>

            <div class="connection-details">

                <p>
                    <strong>Status:</strong>
                    Square is not connected.
                </p>

                <?php if ($lastSyncedAt): ?>
                    <p>
                        Customer data may be stale.
                        Last synced
                        <?= h($lastSyncedAt->timeAgoInWords()) ?>
                        before Square was disconnected.
                    </p>
                <?php endif; ?>

            </div>


            <?php if ($isSuperAdmin): ?>

                <div class="settings-actions">

                    <?= $this->Html->link(
                        'Connect Square',
                        [
                            'controller' => 'Auth',
                            'action' => 'beginAuth'
                        ],
                        [
                            'class' => 'settings-button'
                        ]
                    ) ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>


    <?= $this->Html->link(
        '← Back to Profile',
        [
            'controller' => 'Users',
            'action' => 'profile'
        ],
        [
            'class' => 'settings-back'
        ]
    ) ?>

</div>
