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
                <strong>Willow and Sage</strong>
            </div>

            <div class="login-copy">
                <h2>Slow down, unwind, feel nurtured.</h2>
                <p>
                    Sign in to see your upcoming treatments, review past visits
                    and book your next appointment.
                </p>
            </div>

            <div class="login-meta">
                Wellness studio &middot; Ingle Farm, Adelaide
            </div>

        </section>

        <section class="login-form-panel">

            <div class="login-card">

                <div class="login-header">
                    <h1>Sign in</h1>
                    <p>Enter your details to reach your bookings.</p>
                </div>

                <?= $this->Flash->render() ?>

                <?= $this->Form->create(null, ['class' => 'login-form']) ?>

                <div class="form-group">
                    <?= $this->Form->control('email', [
                        'required' => true,
                        'label' => 'Email address',
                        'placeholder' => 'you@example.com',
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

                <?php /*
                 * A way back out for someone who landed here from a staff-only
                 * URL, or who just wants to look around before signing in.
                 */ ?>
                <?= $this->Html->link(
                    'View homepage',
                    ['controller' => 'Home', 'action' => 'index'],
                    ['class' => 'login-button is-secondary']
                ) ?>

                <p class="auth-switch">
                    New here?
                    <?= $this->Html->link(
                        'Create an account',
                        [
                            'controller' => 'Users',
                            'action' => 'register'
                        ]
                    ) ?>
                </p>

            </div>

        </section>

    </div>

</div>


