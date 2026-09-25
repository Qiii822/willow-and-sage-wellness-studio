<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
use App\Model\Table\UsersTable;

$this->assign('title', 'Reset Password');

$this->Html->css('auth', ['block' => true]);
?>

<div class="auth-page">
    <div class="auth-card">
        <?= $this->Flash->render() ?>

        <div class="auth-header">
            <h1>Reset Password</h1>
            <p>Enter and confirm your new password below.</p>
        </div>

        <?= $this->Form->create($user, ['class' => 'auth-form']) ?>
        <?= $this->Form->control('password', [
            'label' => 'New password',
            'required' => true,
            'value' => '',
            'autocomplete' => 'new-password',
            'minlength' => UsersTable::PASSWORD_MIN,
            'maxlength' => UsersTable::PASSWORD_MAX,
        ]) ?>

        <p class="auth-form-hint"><?= UsersTable::PASSWORD_MIN ?>&ndash;<?= UsersTable::PASSWORD_MAX ?> characters, including an uppercase letter, a lowercase letter and a number.</p>

        <?= $this->Form->control('password_confirm', [
            'type' => 'password',
            'label' => 'Confirm password',
            'required' => true,
            'value' => '',
            'autocomplete' => 'new-password',
            'minlength' => UsersTable::PASSWORD_MIN,
            'maxlength' => UsersTable::PASSWORD_MAX,
        ]) ?>
        <?= $this->Form->button('Save Password', ['class' => 'auth-button']) ?>
        <?= $this->Form->end() ?>
    </div>
</div>