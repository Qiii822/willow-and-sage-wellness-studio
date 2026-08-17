<?php
$this->assign('title', 'Login');

$this->Html->css(
    'login',
    ['block' => true]
);

$this->Html->script(
    'login',
    ['block' => true]
);
?>

<div class="login-page">

    <div class="login-shell">

        <section class="login-visual">

            <div class="login-brand">
                <div class="login-brand-mark">W</div>
                <strong>Wendy Admin</strong>
            </div>

            <div class="login-copy">
                <h2>Customer insights, all in one place.</h2>
                <p>
                    View customer activity, bookings and business performance
                    from one simple dashboard.
                </p>
            </div>

            <div class="login-meta">
                Square Customer Analyser
            </div>

        </section>

        <section class="login-form-panel">

            <div class="login-card">

                <div class="login-header">
                    <h1>Welcome back</h1>
                    <p>Sign in to continue to your dashboard.</p>
                </div>

                <?= $this->Flash->render() ?>

                <?= $this->Form->create(null, ['class' => 'login-form']) ?>

                <div class="form-group">
                    <?= $this->Form->control('email', [
                        'required' => true,
                        'label' => 'Email address',
                        'placeholder' => 'wendy@example.com',
                        'class' => 'form-control',
                        'autocomplete' => 'email',
                    ]) ?>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <div class="password-wrapper">

                        <?= $this->Form->password('password', [
                            'required' => true,
                            'id' => 'password',
                            'class' => 'form-control',
                            'placeholder' => 'Enter your password',
                            'autocomplete' => 'current-password',
                        ]) ?>

                        <button
                            type="button"
                            class="password-toggle"
                            id="toggle-password"
                            aria-label="Show password"
                        >
                            <i data-lucide="eye" id="password-icon"></i>
                        </button>

                    </div>
                </div>

                <div class="forgot-password-link">
                    <?= $this->Html->link(
                        'Forgot password?',
                        [
                            'controller' => 'Users',
                            'action' => 'forgotPassword'
                        ]
                    ) ?>
                </div>

                <?= $this->Form->button('Login', [
                    'class' => 'login-button',
                    'type' => 'submit',
                ]) ?>

                <?= $this->Form->end() ?>

            </div>

        </section>

    </div>

</div>


