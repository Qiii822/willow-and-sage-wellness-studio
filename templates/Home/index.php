<?php
/**
 * Customer facing home page
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User|null $identity
 */

$this->assign('title', 'Willow and Sage Wellness Studio');

$this->Html->css(
    'landing',
    ['block' => true]
);

/*
 * Backdrop photo. Drop any of these into webroot/img/ and it is picked up
 * automatically; with none present the hero falls back to its gradient.
 * First match wins, so `hero.*` overrides the studio's default shot.
 */
$heroImage = null;

foreach ([
    'hero.webp',
    'hero.jpg',
    'hero.png',
    'homepage.webp',
    'homepage.jpg',
] as $candidate) {
    if (file_exists(WWW_ROOT . 'img' . DS . $candidate)) {
        $heroImage = $this->Url->image($candidate);
        break;
    }
}

$initial = strtoupper(mb_substr($identity->first_name ?? 'U', 0, 1));

/*
 * Booking is handled by Square, not here. These links leave the site, so
 * they are real anchors - the remaining nav entries are still inert
 * buttons until the pages behind them exist.
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

        <span class="site-brand">Willow and Sage Wellness Studio</span>

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

                <span class="site-avatar" aria-hidden="true">
                    <?= h($initial) ?>
                </span>

                <?= $this->Html->link(
                    'Log out',
                    ['controller' => 'Users', 'action' => 'logout'],
                    ['class' => 'site-session-link']
                ) ?>

            <?php else : ?>

                <span class="site-avatar" aria-hidden="true">U</span>

                <?= $this->Html->link(
                    'Log in',
                    ['controller' => 'Users', 'action' => 'login'],
                    ['class' => 'site-session-link']
                ) ?>

            <?php endif; ?>

        </div>

    </div>

</header>

<main class="site-main">

    <section
        class="hero<?= $heroImage ? ' has-photo' : '' ?>"
        <?php if ($heroImage) : ?>
            style="--hero-photo: url('<?= h($heroImage) ?>');"
        <?php endif; ?>
    >

        <div class="hero-content">

            <p class="hero-eyebrow">
                Wellness studio &middot; Ingle Farm, Adelaide
            </p>

            <h1 class="hero-title">
                Slow down.<br>
                Unwind.<br>
                Feel nurtured.
            </h1>

            <p class="hero-lead">
                Relaxation massage, facials and natural nail care in a calming
                home-based studio.
            </p>

            <a
                class="hero-cta"
                href="<?= h($bookingUrl) ?>"
                target="_blank"
                rel="noopener"
            >
                Book a treatment
            </a>

            <?php if ($identity) : ?>
                <?php /*
                 * Square's booking page has no prefill parameter, so the
                 * customer types their own email over there. That address is
                 * the only thing tying the booking back to this account, so
                 * spell out which one to use.
                 */ ?>
                <p class="hero-note">
                    Book with <strong><?= h($identity->email) ?></strong>
                    so your appointments show up here.
                </p>
            <?php endif; ?>

        </div>

    </section>

    <section class="feature-strip">

        <div class="feature-strip-inner">

            <div class="section-intro">

                <h2>Service menu</h2>

                <p>
                    Willow &amp; Sage Wellness Studio is a calming home-based
                    wellness space in Ingle Farm, Adelaide, offering relaxation
                    massage, facials, and natural nail care treatments designed
                    to help you slow down, unwind, and feel nurtured.
                </p>

            </div>

            <div class="feature-grid">

                <article class="feature-card">
                    <span class="feature-icon">
                        <i data-lucide="heart"></i>
                    </span>
                    <h3>Relaxation massage</h3>
                    <p>
                        Unhurried, full-body massage to release held tension
                        and quiet a busy head.
                    </p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon">
                        <i data-lucide="sparkles"></i>
                    </span>
                    <h3>Facials</h3>
                    <p>
                        Gentle, skin-first facials chosen to suit how your skin
                        is behaving on the day.
                    </p>
                </article>

                <article class="feature-card">
                    <span class="feature-icon">
                        <i data-lucide="flower"></i>
                    </span>
                    <h3>Natural nail care</h3>
                    <p>
                        Manicures and pedicures using natural products,
                        finished without the rush.
                    </p>
                </article>

            </div>

        </div>

    </section>

</main>

<footer class="site-footer">

    <div class="site-footer-inner">

        <div class="site-footer-brand">
            <strong>Willow and Sage Wellness Studio</strong>
            <span>Ingle Farm, Adelaide</span>
        </div>

        <div class="site-footer-contact">
            <span>Open until 7:00 pm</span>
            <a href="tel:+61458033899">+61 458 033 899</a>
            <a href="mailto:Willowandsagewellness26@gmail.com">
                Willowandsagewellness26@gmail.com
            </a>
        </div>

    </div>

</footer>
