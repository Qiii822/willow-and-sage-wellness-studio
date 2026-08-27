<?php
/**
 * Customer facing home page
 *
 * @var \App\View\AppView $this
 */

// Every string on this page comes from the editor under Website content.
$this->assign('title', $this->SiteContent->get('site.title'));

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
$bookingUrl = $this->SiteContent->raw('booking.url');
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
                <?= $this->SiteContent->get('hero.eyebrow') ?>
            </p>

            <h1 class="hero-title">
                <?= $this->SiteContent->lines('hero.title') ?>
            </h1>

            <p class="hero-lead">
                <?= $this->SiteContent->get('hero.lead') ?>
            </p>

            <a
                class="hero-cta"
                href="<?= h($bookingUrl) ?>"
                target="_blank"
                rel="noopener"
            >
                <?= $this->SiteContent->get('hero.cta') ?>
            </a>


        </div>

    </section>

    <section class="feature-strip">

        <div class="feature-strip-inner">

            <div class="section-intro">

                <h2><?= $this->SiteContent->get('services.heading') ?></h2>

                <p><?= $this->SiteContent->get('services.intro') ?></p>

            </div>

            <div class="feature-grid">

                <?php
                /*
                 * The icons stay in the template: they are part of the design
                 * rather than copy, and picking one is not something the
                 * editor should have to reason about.
                 */
                $serviceIcons = ['heart', 'sparkles', 'flower'];
                ?>

                <?php foreach ($serviceIcons as $index => $icon): ?>
                    <?php $n = $index + 1; ?>

                    <article class="feature-card">
                        <span class="feature-icon">
                            <i data-lucide="<?= h($icon) ?>"></i>
                        </span>
                        <h3><?= $this->SiteContent->get("service.{$n}.title") ?></h3>
                        <p><?= $this->SiteContent->get("service.{$n}.body") ?></p>
                    </article>

                <?php endforeach; ?>

            </div>

        </div>

    </section>

</main>

<?= $this->element('site_footer') ?>
