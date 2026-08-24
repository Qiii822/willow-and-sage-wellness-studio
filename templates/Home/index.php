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

// Booking lives on Square; the hero button leaves the site for it.
$bookingUrl = env(
    'SQUARE_BOOKING_URL',
    'https://book.squareup.com/appointments/r2a8ubnahl2pug/location/LPSB9KGV9MYAJ/services'
    . '?buttonTextColor=ffffff&color=4d4d4d&locale=en-AU&referrer=so'
);
?>

<?= $this->element('site_header') ?>

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

<?= $this->element('site_footer') ?>
