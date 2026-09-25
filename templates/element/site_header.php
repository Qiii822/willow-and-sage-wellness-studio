<?php
/**
 * Public site header, shared by every customer-facing page.
 *
 * Self-contained on purpose: it reads the identity from the request rather
 * than a view variable, so any controller can render it without having to
 * remember to pass anything in.
 *
 * @var \App\View\AppView $this
 */

$identity = $this->request->getAttribute('identity');

$initial = strtoupper(mb_substr($identity->first_name ?? 'U', 0, 1));

/*
 * Loaded here so every page carrying this header gets the header styles and
 * a working dropdown without having to remember either. Repeat calls for
 * the same asset are ignored, so a page already asking for landing.css is
 * unaffected.
 */
$this->Html->css('landing', ['block' => true]);
$this->Html->script('account_menu', ['block' => true]);

/*
 * Booking is handled by Square, not here. That link leaves the site, so it
 * is a real anchor - the remaining nav entries are still inert buttons
 * until the pages behind them exist.
 */
$bookingUrl = env(
    'SQUARE_BOOKING_URL',
    'https://book.squareup.com/appointments/r2a8ubnahl2pug/location/LPSB9KGV9MYAJ/services'
    . '?buttonTextColor=ffffff&color=4d4d4d&locale=en-AU&referrer=so'
);

$navItems = [
    'Gift card',
];
?>

<header class="site-header">

    <div class="site-header-inner">

        <?= $this->Html->link(
            'Willow and Sage Wellness Studio',
            ['controller' => 'Home', 'action' => 'index'],
            ['class' => 'site-brand']
        ) ?>

        <nav class="site-nav" aria-label="Main">

            <a
                class="site-nav-link"
                href="<?= h($bookingUrl) ?>"
                target="_blank"
                rel="noopener"
            >
                Book
            </a>

            <?php foreach ($navItems as $item) : ?>
                <button type="button" class="site-nav-link">
                    <?= h($item) ?>
                </button>
            <?php endforeach; ?>

            <button type="button" class="site-nav-link is-accent">
                My bookings
            </button>

        </nav>

        <div class="site-header-actions">

            <button
                type="button"
                class="site-icon-button"
                aria-label="Notifications"
            >
                <i data-lucide="bell"></i>
            </button>

            <?php if ($identity) : ?>

                <div class="account-menu">

                    <button
                        type="button"
                        class="site-avatar"
                        id="account-menu-button"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="account-menu-panel"
                    >
                        <span aria-hidden="true"><?= h($initial) ?></span>
                        <span class="visually-hidden">Your account</span>
                    </button>

                    <div
                        class="account-menu-panel"
                        id="account-menu-panel"
                        role="menu"
                        aria-labelledby="account-menu-button"
                        hidden
                    >

                        <div class="account-menu-identity">
                            <strong><?= h(trim(
                                ($identity->first_name ?? '') . ' ' .
                                ($identity->surname ?? '')
                            )) ?></strong>
                            <span><?= h($identity->email) ?></span>
                        </div>

                        <?php /*
                         * The customer's own settings page, not the admin
                         * Settings screen: that one is staff-only, so
                         * sending a customer there would sign them out.
                         */ ?>
                        <?= $this->Html->link(
                            'Settings',
                            ['controller' => 'Users', 'action' => 'accountSettings'],
                            [
                                'class' => 'account-menu-item',
                                'role' => 'menuitem',
                            ]
                        ) ?>

                        <?= $this->Form->create(null, [
                            'url' => ['controller' => 'Users', 'action' => 'logout'],
                            'class' => 'account-menu-form',
                        ]) ?>
                        <?= $this->Form->button('Logout', [
                            'class' => 'account-menu-item is-danger',
                            'role' => 'menuitem',
                        ]) ?>
                        <?= $this->Form->end() ?>

                    </div>

                </div>

            <?php else : ?>

                <?php /*
                 * No avatar for a visitor who is not signed in - there is no
                 * account behind it, and the placeholder initial reads as a
                 * half-loaded profile.
                 */ ?>
                <?= $this->Html->link(
                    'Log in',
                    ['controller' => 'Users', 'action' => 'login'],
                    ['class' => 'site-session-link']
                ) ?>

            <?php endif; ?>

        </div>

    </div>

</header>