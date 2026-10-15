<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface|null $squareIdentity
 * @var \Cake\I18n\DateTime|null $lastSyncedAt
 * @var bool $justConnected
 * @var array<string, array{label: string, type: string, default: string}> $emailFields
 * @var array<string, string> $emailValues
 * @var array<string, string> $emailPlaceholders
 */

$this->Html->css('settings', ['block' => true]);
$this->Html->css('confirm_modal', ['block' => true]);
$this->Html->css('email_content', ['block' => true]);

$this->Html->script('email_content', ['block' => true]);

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

        <?php /*
         * The result of a sync or a disconnect, shown here rather than at
         * the top of the page: it is about this card, and it is read right
         * where the button that caused it sits.
         */ ?>
        <?php $squareResult = $this->Flash->render('square'); ?>

        <?php if ($squareResult !== null && trim($squareResult) !== ''): ?>
            <div class="settings-result">
                <?= $squareResult ?>
            </div>
        <?php endif; ?>

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

                <p>
                    Customers, bookings and payments from Square are still
                    stored here. Clearing them keeps the treatment menu,
                    offers, site content and everyone's login.
                </p>

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

                    <?= $this->Form->create(null, [
                        'id' => 'clear-data-form',
                        'url' => [
                            'controller' => 'Auth',
                            'action' => 'clearData'
                        ]
                    ]) ?>

                    <?= $this->Form->button(
                        'Clear Square data',
                        [
                            'class' => 'settings-button danger'
                        ]
                    ) ?>

                    <?= $this->Form->end() ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

    <?php /*
     * Only the wording is edited here. Which account email is sent from is
     * set by the developers in .env - SMTP servers and API tokens are not
     * something the studio should ever have to deal with.
     */ ?>
    <div class="settings-card" id="email-content">

        <div class="settings-card-header">
            <div>
                <h2>Reminder email</h2>
                <p>
                    The email that invites customers who have not booked in a
                    while to come back. Change the words; the design and the
                    booking button stay as they are.
                </p>
            </div>
        </div>

        <?php $emailResult = $this->Flash->render('email'); ?>

        <?php if ($emailResult !== null && trim($emailResult) !== ''): ?>
            <div class="settings-result">
                <?= $emailResult ?>
            </div>
        <?php endif; ?>

        <div class="email-editor">

            <?= $this->Form->create(null, [
                'url' => ['action' => 'saveEmailContent'],
                'class' => 'email-form',
                'id' => 'email-content-form',
            ]) ?>

            <p class="email-placeholders">
                Filled in for each customer:
                <?php foreach ($emailPlaceholders as $tag => $meaning): ?>
                    <span><code><?= h($tag) ?></code> <?= h(strtolower($meaning)) ?></span>
                <?php endforeach; ?>
            </p>

            <?php foreach ($emailFields as $key => $field): ?>
                <?php
                $id = 'email-' . str_replace('.', '-', $key);
                // The part after "rebooking." - what the preview is keyed on.
                $previewKey = substr($key, strlen('rebooking.'));
                ?>
                <div class="email-field">
                    <label for="<?= h($id) ?>"><?= h($field['label']) ?></label>

                    <?php if ($field['type'] === 'textarea'): ?>
                        <textarea
                            id="<?= h($id) ?>"
                            name="email[<?= h($key) ?>]"
                            rows="<?= substr_count($emailValues[$key], "\n") + 2 ?>"
                            data-preview-field="<?= h($previewKey) ?>"
                            data-default="<?= h($field['default']) ?>"
                        ><?= h($emailValues[$key]) ?></textarea>
                    <?php else: ?>
                        <input
                            type="text"
                            id="<?= h($id) ?>"
                            name="email[<?= h($key) ?>]"
                            value="<?= h($emailValues[$key]) ?>"
                            data-preview-field="<?= h($previewKey) ?>"
                            data-default="<?= h($field['default']) ?>"
                        >
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <p class="email-hint">
                A field left empty goes back to its original wording.
            </p>

            <div class="settings-actions">
                <?= $this->Form->button('Save email', ['class' => 'settings-button']) ?>
            </div>

            <?= $this->Form->end() ?>

            <?php /*
             * Its own form, so pressing Enter in a field saves rather than
             * resetting everything.
             */ ?>
            <?= $this->Form->create(null, [
                'url' => ['action' => 'saveEmailContent'],
                'class' => 'email-reset-form',
            ]) ?>
            <?= $this->Form->hidden('reset', ['value' => 1]) ?>
            <?= $this->Form->button('Restore original wording', [
                'class' => 'settings-button secondary',
                'confirm' => 'Put every part of the reminder email back to its original wording?',
            ]) ?>
            <?= $this->Form->end() ?>

            <?php
            /*
             * Rendered from the same element the real email uses, with an
             * example customer, and updated by the script as fields change.
             */
            $sample = ['{name}' => 'Sarah Mitchell', '{days}' => '42'];
            $preview = [];

            foreach ($emailValues as $key => $value) {
                $preview[substr($key, strlen('rebooking.'))] = strtr($value, $sample);
            }
            ?>
            <aside class="email-preview" aria-label="Preview">
                <p class="email-preview-label">
                    Preview &middot; as Sarah Mitchell, 42 days since her last visit
                </p>
                <p class="email-preview-subject">
                    <strong>Subject:</strong>
                    <span data-email-field="subject"><?= h($preview['subject']) ?></span>
                </p>
                <div
                    class="email-preview-frame"
                    data-sample-name="Sarah Mitchell"
                    data-sample-days="42"
                >
                    <?= $this->element('email/rebooking_reminder', ['content' => $preview]) ?>
                </div>
            </aside>

        </div>

    </div>

    <div class="confirm-modal-overlay" id="clear-data-confirm-overlay" data-form-id="clear-data-form" hidden>
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="clear-data-confirm-title">
            <h2 id="clear-data-confirm-title">Clear Square data?</h2>
            <p>This permanently deletes the customers, bookings and payments stored here, and the sync history. It cannot be undone, but reconnecting Square and syncing will bring them back.</p>
            <div class="confirm-modal-actions">
                <button type="button" class="settings-button secondary" id="clear-data-confirm-cancel">Cancel</button>
                <button type="button" class="settings-button danger" id="clear-data-confirm-ok">Clear data</button>
            </div>
        </div>
    </div>

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
