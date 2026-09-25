<?php
/**
 * Public signup page
 *
 * Shares the login page's shell styles so the two screens match.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */

use App\Model\Table\UsersTable;

$this->assign('title', 'Create Account');

$this->Html->css(
    'login',
    ['block' => true]
);

$this->Html->css(
    'password_toggle',
    ['block' => true]
);

$this->Html->script(
    'field_counter',
    ['block' => true]
);

$this->Html->script(
    'password_toggle',
    ['block' => true]
);

$this->Html->script(
    'submit_guard',
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
                <h2>Make time for yourself.</h2>
                <p>
                    Create an account to book relaxation massage, facials and
                    natural nail care, and to keep your bookings in one place.
                </p>
            </div>

            <div class="login-meta">
                Wellness studio &middot; Ingle Farm, Adelaide
            </div>

        </section>

        <section class="login-form-panel">

            <div class="login-card">

                <div class="login-header">
                    <h1>Create your account</h1>
                    <p>It takes about a minute.</p>
                </div>

                <?= $this->Flash->render() ?>

                <?= $this->Form->create($user, [
                    'class' => 'login-form',
                    'data-guard' => '1',
                ]) ?>

                <div class="form-row">

                    <div class="form-group">
                        <?= $this->Form->control('first_name', [
                            'required' => true,
                            'label' => 'First name',
                            'placeholder' => 'Amelia',
                            'class' => 'form-control',
                            'autocomplete' => 'given-name',
                            'maxlength' => UsersTable::NAME_MAX,
                            'data-count' => '1',
                        ]) ?>

                    </div>

                    <div class="form-group">
                        <?= $this->Form->control('surname', [
                            'required' => true,
                            'label' => 'Surname',
                            'placeholder' => 'Nguyen',
                            'class' => 'form-control',
                            'autocomplete' => 'family-name',
                            'maxlength' => UsersTable::NAME_MAX,
                            'data-count' => '1',
                        ]) ?>

                    </div>

                </div>

                <div class="form-group">
                    <?= $this->Form->control('email', [
                        'type' => 'email',
                        'required' => true,
                        'label' => 'Email address',
                        'placeholder' => 'you@example.com',
                        'class' => 'form-control',
                        'autocomplete' => 'email',
                        'maxlength' => UsersTable::EMAIL_MAX,
                        'data-count' => '1',
                    ]) ?>
                </div>

                <div class="form-group">
                    <?= $this->Form->control('phone', [
                        'type' => 'tel',
                        'required' => true,
                        'label' => 'Mobile number',
                        'placeholder' => '0412 345 678',
                        'class' => 'form-control',
                        'autocomplete' => 'tel',
                        'inputmode' => 'tel',
                        'maxlength' => 20,
                    ]) ?>

                    <p class="field-hint">
                        Use this same number when you book, so your
                        appointments show up in your account.
                    </p>
                </div>

                <div class="form-group">
                    <?= $this->Form->control('password', [
                        'type' => 'password',
                        'required' => true,
                        'label' => 'Password',
                        'placeholder' => UsersTable::PASSWORD_MIN . '-' . UsersTable::PASSWORD_MAX . ' characters',
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                        'minlength' => UsersTable::PASSWORD_MIN,
                        'maxlength' => UsersTable::PASSWORD_MAX,
                        'data-toggle' => '1',
                    ]) ?>

                    <p class="field-hint">
                        Use <?= UsersTable::PASSWORD_MIN ?>&ndash;<?= UsersTable::PASSWORD_MAX ?>
                        characters, with an uppercase letter, a lowercase
                        letter and a number.
                    </p>
                </div>

                <div class="form-group">
                    <?= $this->Form->control('password_confirm', [
                        'type' => 'password',
                        'required' => true,
                        'label' => 'Confirm password',
                        'placeholder' => 'Re-enter your password',
                        'class' => 'form-control',
                        'autocomplete' => 'new-password',
                        'minlength' => UsersTable::PASSWORD_MIN,
                        'maxlength' => UsersTable::PASSWORD_MAX,
                        'data-toggle' => '1',
                    ]) ?>

                    <p class="field-hint">
                        Must match the password above
                    </p>
                </div>

                <?= $this->Form->button('Create account', [
                    'class' => 'login-button',
                    'type' => 'submit',
                    'data-busy-label' => 'Creating account…',
                ]) ?>

                <?= $this->Form->end() ?>

                <p class="auth-switch">
                    Already have an account?
                    <?= $this->Html->link(
                        'Sign in',
                        [
                            'controller' => 'Users',
                            'action' => 'login'
                        ]
                    ) ?>
                </p>

            </div>

        </section>

    </div>

</div>
