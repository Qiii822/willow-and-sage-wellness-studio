<?php
/**
 * Public site header, shared by every customer-facing page.
 *
 * @var \App\View\AppView $this
 */

/*
 * Loaded here so every page carrying this header gets the header styles
 * without having to remember. Repeat calls for the same asset are ignored,
 * so a page already asking for landing.css is unaffected.
 */
$this->Html->css('landing', ['block' => true]);

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

            <?php /*
             * "My bookings" belongs to a signed-in customer, so it has no
             * place on a site with no accounts.
             */ ?>
            <?php foreach ($navItems as $item) : ?>
                <button type="button" class="site-nav-link">
                    <?= h($item) ?>
                </button>
            <?php endforeach; ?>

        </nav>

        <?php
        /*
         * Public-only build: there is no sign-in here, so the header carries
         * nothing that implies an account - no login link, no avatar menu,
         * no notification bell.
         *
         * The staff screens still exist and still require a login. They are
         * simply not reachable from this site.
         */
        ?>

    </div>

</header>