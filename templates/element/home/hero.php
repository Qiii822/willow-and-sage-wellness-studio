<?php
/**
 * Home page block: the welcome banner.
 *
 * @var \App\View\AppView $this
 * @var string $variant Arrangement chosen in the editor.
 */

$heroImage = $this->SiteContent->heroImageUrl();
$bookingUrl = $this->SiteContent->raw('booking.url');
?>

<section
    class="hero<?= $heroImage ? ' has-photo' : '' ?> hero-align-<?= h($variant) ?>"
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
