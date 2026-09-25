<?php
/**
 * Home page block: the welcome banner.
 *
 * @var \App\View\AppView $this
 * @var string $variant Arrangement chosen in the editor.
 */

$heroImage = $this->SiteContent->heroImageUrl();
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

        <?php /*
         * Goes to the treatment menu rather than into the booking flow:
         * someone who has just landed here does not yet know what is on
         * offer, and a list of times is a question they cannot answer yet.
         * Booking is the step after reading.
         */ ?>
        <?= $this->Html->link(
            $this->SiteContent->get('hero.cta'),
            ['controller' => 'Services', 'action' => 'index'],
            ['class' => 'hero-cta', 'escape' => false]
        ) ?>

    </div>

</section>
