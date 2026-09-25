<?php
$this->assign('title', 'Forgot Password');

$this->Html->css(
    'auth',
    ['block' => true]
);
?>

<div class="auth-page">

    <div class="auth-card">

        <?= $this->Flash->render() ?>

        <div class="auth-header">
            <h1>Forgot Password</h1>

            <p>
                Enter your email address and we'll help you reset your password.
            </p>
        </div>

        <?= $this->Form->create(null, [
            'class' => 'auth-form'
        ]) ?>

        <?= $this->Form->control('email', [
            'type' => 'email',
            'label' => 'Email address',
            'required' => true,
            'placeholder' => 'you@example.com',
            'maxlength' => \App\Model\Table\UsersTable::EMAIL_MAX,
        ]) ?>

        <div class="auth-actions">

            <?= $this->Form->button(
                'Reset Password',
                [
                    'class' => 'auth-button'
                ]
            ) ?>

            <?= $this->Html->link(
                'Back to Login',
                [
                    'controller' => 'Users',
                    'action' => 'login',
                ],
                [
                    'class' => 'auth-button secondary'
                ]
            ) ?>

        </div>

        <?= $this->Form->end() ?>

    </div>

</div>
