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
 * "Book" goes to the treatment menu rather than to Square. Booking still
 * finishes on Square, but choosing what to book happens here first, so the
 * customer arrives at the calendar already knowing what they want.
 *
 * The remaining nav entries are still inert buttons until the pages behind
 * them exist.
 */
$navItems = [
    'Gift card',
];
?>

<header class="site-header">

    <div class="site-header-inner">

        <?= $this->Html->link(
            $this->SiteContent->get('site.brand'),
            ['controller' => 'Home', 'action' => 'index'],
            ['class' => 'site-brand', 'escape' => false]
        ) ?>

        <nav class="site-nav" aria-label="Main">

            <?= $this->Html->link(
                'Services',
                ['controller' => 'Services', 'action' => 'index'],
                ['class' => 'site-nav-link']
            ) ?>

            <?= $this->Html->link(
                'Book',
                ['controller' => 'Services', 'action' => 'index'],
                ['class' => 'site-nav-link']
            ) ?>

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