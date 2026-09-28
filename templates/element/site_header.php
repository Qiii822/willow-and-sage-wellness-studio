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
 * Booking now happens here from start to finish, so "Book" opens the flow
 * itself. The read-only menu is still a page of its own, reached from the
 * home page, for someone who wants to know what is on offer before they
 * commit to choosing.
 *
 * The remaining nav entries are still inert buttons until the pages behind
 * them exist.
 */
$navItems = [
    'Gift card',
];

/*
 * Offers are a section of the home page rather than a page of their own, so
 * this entry is an anchor. It is only worth showing when there is something
 * to land on: both an offer to show, and the block still switched on in the
 * layout editor. Otherwise the link would scroll to nothing.
 */
$locator = \Cake\ORM\TableRegistry::getTableLocator();

$showOffers = $locator->get('Offers')->current() !== []
    && in_array(
        'offers',
        array_column($locator->get('SiteSections')->visibleLayout(), 'key'),
        true
    );
?>

<header class="site-header">

    <div class="site-header-inner">

        <?= $this->Html->link(
            $this->SiteContent->get('site.brand'),
            ['controller' => 'Home', 'action' => 'index'],
            ['class' => 'site-brand', 'escape' => false]
        ) ?>

        <nav class="site-nav" aria-label="Main">

            <?php if ($showOffers): ?>
                <?= $this->Html->link(
                    'Offers',
                    ['controller' => 'Home', 'action' => 'index', '#' => 'offers'],
                    ['class' => 'site-nav-link']
                ) ?>
            <?php endif; ?>

            <?= $this->Html->link(
                'Book',
                ['controller' => 'Book', 'action' => 'index'],
                ['class' => 'site-nav-link']
            ) ?>

            <?php /*
             * No account behind this one: the customer proves the phone
             * number they booked with, and that is the whole sign-in.
             */ ?>
            <?= $this->Html->link(
                'My bookings',
                ['controller' => 'MyBookings', 'action' => 'index'],
                ['class' => 'site-nav-link']
            ) ?>

            <?php foreach ($navItems as $item) : ?>
                <button type="button" class="site-nav-link">
                    <?= h($item) ?>
                </button>
            <?php endforeach; ?>

        </nav>

        <?php
        /*
         * Staff who are already signed in get their account here; everyone
         * else gets nothing at all.
         *
         * Deliberately one-way: there is no "log in" link, because this is
         * the customer's site and an invitation to sign in would only
         * puzzle them. Someone signed in arrived through the staff screens
         * and is only being offered a way back out.
         */
        ?>
        <?php $identity = $this->request->getAttribute('identity'); ?>

        <?php if ($identity): ?>
            <?= $this->element('site_account', ['identity' => $identity]) ?>
        <?php endif; ?>

    </div>

</header>

<?php /*
 * Directly under the header rather than above it, and held to the same
 * width as the page it belongs to. A message about what just happened
 * belongs with the page, not stranded over the studio's name.
 */ ?>
<?php $flash = $this->Flash->render(); ?>

<?php if ($flash !== null && trim($flash) !== ''): ?>
    <div class="site-flash">
        <?= $flash ?>
    </div>
<?php endif; ?>