<?php
/**
 * The signed-in staff member's account control on the public site.
 *
 * Only rendered when somebody is signed in - a customer never sees it, and
 * there is no way to sign in from here. It exists so a staff member who
 * has wandered onto the public pages can get back to the dashboard or sign
 * out, rather than being stranded.
 *
 * The markup matches the account-menu styles that were already in
 * landing.css but had no template using them.
 *
 * @var \App\View\AppView $this
 * @var \Authentication\IdentityInterface|\Cake\Datasource\EntityInterface $identity
 */

$first = trim((string)($identity->first_name ?? ''));
$last = trim((string)($identity->last_name ?? ''));
$name = trim($first . ' ' . $last);
$email = trim((string)($identity->email ?? ''));

// The initial the avatar shows. Falls back to the email so the circle is
// never blank for an account with no name on it.
$initial = mb_strtoupper(mb_substr($name !== '' ? $name : ($email !== '' ? $email : '?'), 0, 1));

/*
 * Loaded from here rather than the layout: no signed-in staff member, no
 * menu, nothing for the script to do.
 *
 * account_menu.js was already on main when this was written, with nothing
 * using it. The ids below are the hooks it looks for - the markup was
 * changed to match the script rather than the other way round, so a file
 * someone else had already committed is not overwritten.
 */
$this->Html->script('account_menu', ['block' => true]);
?>

<div class="account-menu">

    <button
        type="button"
        class="site-avatar"
        id="account-menu-button"
        aria-expanded="false"
        aria-haspopup="true"
        aria-controls="account-menu-panel"
    >
        <span aria-hidden="true"><?= h($initial) ?></span>
        <span class="visually-hidden">
            Account<?= $name !== '' ? ' - ' . h($name) : '' ?>
        </span>
    </button>

    <?php /*
     * Hidden in the markup rather than by the script, so it is not flashed
     * on screen before the script runs. Without scripting it stays shut and
     * the whole control does nothing - which is why signing out also exists
     * on every staff screen, where it is not hidden behind a toggle.
     */ ?>
    <div class="account-menu-panel" id="account-menu-panel" hidden>

        <?php if ($name !== '' || $email !== ''): ?>
            <div class="account-menu-identity">
                <?php if ($name !== ''): ?>
                    <strong><?= h($name) ?></strong>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                    <span><?= h($email) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?= $this->Html->link('Dashboard', [
            'controller' => 'Dashboard',
            'action' => 'index',
        ], ['class' => 'account-menu-item']) ?>

        <?php /*
         * A form, not a link: signing out changes something, and a link
         * would let it happen on a stray prefetch.
         */ ?>
        <?= $this->Form->create(null, [
            'url' => ['controller' => 'Users', 'action' => 'logout'],
            'class' => 'account-menu-form',
        ]) ?>

        <?= $this->Form->button('Log out', [
            'class' => 'account-menu-item is-danger',
        ]) ?>

        <?= $this->Form->end() ?>

    </div>

</div>
