<?php
$this->assign('title', 'Forgot Password');

$this->Html->css(
    'forgot_password',
    ['block' => true]
);
?>

<div class="forgot-password-page">

    <div class="forgot-password-card">

        <?= $this->Flash->render() ?>

        <div class="forgot-password-header">
            <h1>Forgot Password</h1>

            <p>
                Enter your email address and we'll help you reset your password.
            </p>
        </div>

        <?= $this->Form->create(null, [
            'class' => 'forgot-password-form'
        ]) ?>

        <?= $this->Form->control('email', [
            'type' => 'email',
            'label' => 'Email address',
            'required' => true,
            'placeholder' => 'wendy@example.com',
        ]) ?>

        <div class="forgot-password-actions">

            <?= $this->Form->button(
                'Reset Password',
                [
                    'class' => 'forgot-password-button'
                ]
            ) ?>

            <?= $this->Html->link(
                'Back to Login',
                [
                    'controller' => 'Users',
                    'action' => 'login',
                ],
                [
                    'class' => 'forgot-password-button secondary'
                ]
            ) ?>

        </div>

        <?= $this->Form->end() ?>

    </div>

</div>
