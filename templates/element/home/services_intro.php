<?php
/**
 * Home page block: heading and paragraph about the studio, with an
 * optional photo.
 *
 * The photo fills the space a short paragraph leaves on a wide screen:
 * beside the wording when it is left aligned, under it when centred. With
 * none uploaded the wording stands alone, held to the usual gap from the
 * block below.
 *
 * @var \App\View\AppView $this
 * @var string $variant Arrangement chosen in the editor.
 */

$photo = $this->SiteContent->imageUrl('services.image');
?>

<section class="feature-strip feature-strip-intro">

    <div class="feature-strip-inner">

        <div class="intro-block intro-block-<?= h($variant) ?><?= $photo ? ' has-photo' : '' ?>">

            <div class="section-intro section-align-<?= h($variant) ?>">

                <h2><?= $this->SiteContent->get('services.heading') ?></h2>

                <p><?= $this->SiteContent->get('services.intro') ?></p>

            </div>

            <?php if ($photo): ?>
                <?php /* Decorative; the wording beside it carries the meaning. */ ?>
                <div class="intro-photo">
                    <img src="<?= h($photo) ?>" alt="" loading="lazy">
                </div>
            <?php endif; ?>

        </div>

    </div>

</section>
