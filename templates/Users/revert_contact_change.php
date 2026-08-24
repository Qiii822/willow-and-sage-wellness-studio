<?php
/**
 * Confirmation step for undoing a contact change.
 *
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface $revert
 * @var string $token
 */

use App\Utility\PhoneNumber;

$this->assign('title', 'Undo Contact Change');

$this->Html->css('auth', ['block' => true]);

$isPhone = $revert->field === 'phone';
$label = $isPhone ? 'mobile number' : 'email address';

$present = fn (string $value): string => $isPhone
    ? (string)PhoneNumber::format($value)
    : $value;
?>

<div class="auth-page">

    <div class="auth-card">

        <?= $this->Flash->render() ?>

        <div class="auth-header">
            <h1>Undo this change?</h1>

            <p>
                The <?= h($label) ?> on this account was changed
                from <strong><?= h($present($revert->previous_value)) ?></strong>
                to <strong><?= h($present($revert->new_value)) ?></strong>.
            </p>
        </div>

        <p class="auth-form-hint">
            Confirming will put <strong><?= h($present($revert->previous_value)) ?></strong>
            back on the account and email you a link to set a new password,
            so whoever made the change can no longer sign in.
        </p>

        <?= $this->Form->create(null, [
            'class' => 'auth-form',
            'url' => ['action' => 'revertContactChange', $token],
        ]) ?>

        <div class="auth-actions">

            <?= $this->Form->button('Yes, undo it', [
                'class' => 'auth-button',
            ]) ?>

            <?= $this->Html->link(
                'This was me - leave it',
                ['controller' => 'Users', 'action' => 'login'],
                ['class' => 'auth-button secondary']
            ) ?>

        </div>

        <?= $this->Form->end() ?>

    </div>

</div>
