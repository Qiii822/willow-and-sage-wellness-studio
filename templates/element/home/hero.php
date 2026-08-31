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
         * Goes to the treatment menu, not straight to Square: someone who
         * has just landed here does not yet know what is on offer, and
         * being handed a booking calendar is a question they cannot answer
         * yet. Square is the step after choosing.
         */ ?>
        <?= $this->Html->link(
            $this->SiteContent->get('hero.cta'),
            ['controller' => 'Services', 'action' => 'index'],
            ['class' => 'hero-cta', 'escape' => false]
        ) ?>

    </div>

</section>
