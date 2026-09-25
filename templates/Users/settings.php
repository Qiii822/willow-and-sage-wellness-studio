<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface|null $squareIdentity
 * @var \Cake\I18n\DateTime|null $lastSyncedAt
 * @var bool $justConnected
 */

$this->Html->css('settings', ['block' => true]);
$this->Html->css('confirm_modal', ['block' => true]);
$this->Html->css('mail_settings', ['block' => true]);
$this->Html->css('password_toggle', ['block' => true]);

$this->Html->script('mail_settings', ['block' => true]);
$this->Html->script('password_toggle', ['block' => true]);
$this->Html->script('submit_guard', ['block' => true]);

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
                        'id' => 'disconnect-square-form',
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
                            'class' => 'settings-button danger'
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

    <?php if ($isSuperAdmin): ?>

        <div class="settings-card">

            <div class="settings-card-header">

                <div>
                    <h2>Email notifications</h2>
                    <p>
                        <?php if (!empty($mailSettings)): ?>
                            Password resets and verification codes are sent
                            from <?= h($mailSettings->from_email) ?>.
                        <?php else: ?>
                            Not set up yet. Connect an email account so the
                            site can send password resets and verification
                            codes.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="connection-status<?= empty($mailSettings) ? ' is-off' : '' ?>">
                    <span class="connection-dot"></span>
                    <?= empty($mailSettings) ? 'Not set up' : 'Connected' ?>
                </div>

            </div>

            <?php if (!empty($mailKeyMissing)): ?>
                <p class="settings-warning">
                    <strong>Set a security salt first.</strong>
                    <code>config/app_local.php</code> still has the placeholder
                    <code>__SALT__</code>, so there is no key to encrypt the
                    password with. Replace it with 32 or more random
                    characters, then connect an account.
                </p>
            <?php endif; ?>

            <?= $this->Form->create(null, [
                'url' => ['action' => 'saveMailSettings'],
                'class' => 'mail-form',
                'data-guard' => '1',
            ]) ?>

            <p class="mail-step">Step 1 &mdash; Choose your email provider</p>

            <div class="mail-providers">

                <?php
                $currentProvider = $mailSettings->provider ?? array_key_first($mailProviders);
                $currentMode = $mailSettings->auth_mode ?? null;
                ?>

                <?php foreach ($mailProviders as $key => $provider): ?>
                    <?php $checked = $currentProvider === $key; ?>

                    <?php
                    /*
                     * A provider with one mode has no radio of its own to
                     * carry the server details, so they ride on the provider
                     * instead. With two modes the mode radios supply them.
                     */
                    $only = count($provider['modes']) === 1
                        ? reset($provider['modes'])
                        : null;
                    ?>

                    <label class="mail-provider<?= $checked ? ' is-selected' : '' ?>">
                        <input
                            type="radio"
                            name="provider"
                            value="<?= h($key) ?>"
                            <?php if ($only): ?>
                                data-host="<?= h((string)$only['host']) ?>"
                                data-port="<?= h((string)$only['port']) ?>"
                            <?php endif; ?>
                            <?= $checked ? 'checked' : '' ?>
                        >
                        <span class="mail-provider-name"><?= h($provider['label']) ?></span>
                        <span class="mail-provider-blurb"><?= h($provider['blurb']) ?></span>
                    </label>

                <?php endforeach; ?>

            </div>

            <p class="mail-step">Step 2 &mdash; Enter your login details</p>

            <?php
            /*
             * One block per provider, only the selected one shown. Each
             * carries its modes as radios so the browser posts exactly the
             * pairing that was on screen.
             */
            ?>
            <?php foreach ($mailProviders as $key => $provider): ?>
                <?php if (count($provider['modes']) < 2) { continue; } ?>

                <div class="mail-modes" data-for-provider="<?= h($key) ?>" hidden>

                    <?php foreach ($provider['modes'] as $mode => $preset): ?>
                        <?php
                        $modeChecked = $currentProvider === $key
                            ? ($currentMode ?? array_key_first($provider['modes'])) === $mode
                            : array_key_first($provider['modes']) === $mode;
                        ?>

                        <label class="mail-mode<?= $modeChecked ? ' is-selected' : '' ?>">
                            <input
                                type="radio"
                                name="auth_mode"
                                value="<?= h($mode) ?>"
                                data-host="<?= h((string)$preset['host']) ?>"
                                data-port="<?= h((string)$preset['port']) ?>"
                                <?= $modeChecked ? 'checked' : '' ?>
                            >
                            <span><?= h($preset['label']) ?></span>
                        </label>

                    <?php endforeach; ?>

                </div>

                <?php foreach ($provider['modes'] as $mode => $preset): ?>
                    <?php if (empty($preset['hint'])) { continue; } ?>
                    <p
                        class="mail-hint mail-mode-hint"
                        data-for-provider="<?= h($key) ?>"
                        data-for-mode="<?= h($mode) ?>"
                        hidden
                    ><?= h($preset['hint']) ?></p>
                <?php endforeach; ?>

            <?php endforeach; ?>

            <div class="mail-grid">

                <div class="mail-field" data-smtp-only>
                    <label for="mail-username">Username / email address</label>
                    <?= $this->Form->text('username', [
                        'id' => 'mail-username',
                        'class' => 'mail-input',
                        'value' => $mailSettings->username ?? '',
                        'autocomplete' => 'off',
                    ]) ?>
                </div>

                <div class="mail-field">
                    <label for="mail-password">
                        <span data-label-smtp>Password / app key</span>
                        <span data-label-api hidden>API token</span>
                        <?php if (!empty($mailSettings)): ?>
                            <span class="mail-optional">leave blank to keep the current one</span>
                        <?php endif; ?>
                    </label>
                    <?= $this->Form->password('password', [
                        'id' => 'mail-password',
                        'class' => 'mail-input',
                        'value' => '',
                        'autocomplete' => 'new-password',
                        'data-toggle' => '1',
                    ]) ?>
                    <p class="mail-hint" data-hint-api hidden>
                        Paste the token exactly as your provider shows it.
                        Nothing else is needed.
                    </p>
                </div>

                <div class="mail-field" data-smtp-only>
                    <label for="mail-host">SMTP server</label>
                    <?= $this->Form->text('host', [
                        'id' => 'mail-host',
                        'class' => 'mail-input',
                        'value' => $mailSettings->host ?? '',
                        'placeholder' => 'Filled in from the provider above',
                    ]) ?>
                </div>

                <div class="mail-field mail-field-narrow" data-smtp-only>
                    <label for="mail-port">Port</label>
                    <?= $this->Form->number('port', [
                        'id' => 'mail-port',
                        'class' => 'mail-input',
                        'value' => $mailSettings->port ?? 587,
                        'min' => 1,
                        'max' => 65535,
                    ]) ?>
                </div>

                <div class="mail-field">
                    <label for="mail-from">Send emails from</label>
                    <?= $this->Form->email('from_email', [
                        'id' => 'mail-from',
                        'class' => 'mail-input',
                        'required' => true,
                        'value' => $mailSettings->from_email ?? '',
                        'placeholder' => 'hello@demomailtrap.co',
                    ]) ?>
                    <p class="mail-hint">
                        The address messages are sent <em>from</em>. It must be
                        one your provider has authorised &mdash; on a free
                        Mailtrap account that is their demo address, not your
                        own. This is not where the test is sent.
                    </p>
                </div>

                <div class="mail-field">
                    <label for="mail-from-name">Sender name</label>
                    <?= $this->Form->text('from_name', [
                        'id' => 'mail-from-name',
                        'class' => 'mail-input',
                        'value' => $mailSettings->from_name ?? 'Willow and Sage Wellness Studio',
                    ]) ?>
                </div>

            </div>

            <label class="mail-checkbox" data-smtp-only>
                <?= $this->Form->checkbox('tls', [
                    'checked' => (bool)($mailSettings->tls ?? true),
                    'hiddenField' => true,
                ]) ?>
                Use TLS (recommended)
            </label>

            <?php $identity = $this->request->getAttribute('identity'); ?>

            <div class="mail-field">
                <label for="mail-test-to">Send test message to</label>
                <?= $this->Form->email('test_to', [
                    'id' => 'mail-test-to',
                    'class' => 'mail-input',
                    'required' => true,
                    'value' => $identity->email ?? '',
                ]) ?>
                <p class="mail-hint">
                    Where to send the one-off message that proves these
                    settings work. Use any inbox you can open.
                </p>
            </div>

            <p class="mail-note">
                Nothing is stored unless that message goes through, so broken
                settings cannot be saved by mistake.
            </p>

            <div class="mail-actions">

                <?= $this->Form->button('Save settings', [
                    'class' => 'settings-button',
                    'data-busy-label' => 'Testing connection...',
                ]) ?>

                <?= $this->Form->end() ?>

                <?php if (!empty($mailSettings)): ?>
                    <?= $this->Form->create(null, [
                        'url' => ['action' => 'disconnectMailSettings'],
                        'class' => 'mail-disconnect-form',
                    ]) ?>
                    <?= $this->Form->button('Disconnect', [
                        'class' => 'settings-button secondary',
                    ]) ?>
                    <?= $this->Form->end() ?>
                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>

    <div class="confirm-modal-overlay" id="disconnect-confirm-overlay" data-form-id="disconnect-square-form" hidden>
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="disconnect-confirm-title">
            <h2 id="disconnect-confirm-title">Disconnect Square?</h2>
            <p>This will revoke Wendy Admin's access to the connected Square account.</p>
            <div class="confirm-modal-actions">
                <button type="button" class="settings-button secondary" id="disconnect-confirm-cancel">Cancel</button>
                <button type="button" class="settings-button danger" id="disconnect-confirm-ok">Disconnect Square</button>
            </div>
        </div>
    </div>

</div>

<?= $this->Html->script('confirm_modal', ['block' => true]) ?>
