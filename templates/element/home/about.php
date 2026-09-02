<?php
/**
 * Home page block: who Wendy is.
 *
 * @var \App\View\AppView $this
 * @var string $variant Arrangement chosen in the editor.
 */

$photo = $this->SiteContent->imageUrl('about.image');

$this->Html->css('about', ['block' => true]);
?>

<section class="feature-strip" id="about">

    <div class="feature-strip-inner">

        <div class="split<?= $photo ? ' split-' . h($variant) : ' split-text-only' ?>">

            <?php if ($photo): ?>
                <?php /*
                 * Decorative: the heading and text name her and say what she
                 * does, so alt text here would only repeat the paragraph
                 * sitting next to it.
                 */ ?>
                <div class="split-media">
                    <img src="<?= h($photo) ?>" alt="" loading="lazy">
                </div>
            <?php endif; ?>

            <div class="split-body">

                <h2><?= $this->SiteContent->get('about.heading') ?></h2>

                <?= $this->SiteContent->paragraphs('about.body') ?>

            </div>

        </div>

    </div>

</section>
