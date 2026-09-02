<?php
/**
 * Home page block: comfort, hygiene and safety.
 *
 * @var \App\View\AppView $this
 * @var string $variant Arrangement chosen in the editor.
 */

$photo = $this->SiteContent->imageUrl('safety.image');

$this->Html->css('about', ['block' => true]);
?>

<section class="feature-strip" id="comfort">

    <div class="feature-strip-inner">

        <div class="split<?= $photo ? ' split-' . h($variant) : ' split-text-only' ?>">

            <?php if ($photo): ?>
                <?php /* Decorative; the wording beside it carries the meaning. */ ?>
                <div class="split-media">
                    <img src="<?= h($photo) ?>" alt="" loading="lazy">
                </div>
            <?php endif; ?>

            <div class="split-body">

                <h2><?= $this->SiteContent->get('safety.heading') ?></h2>

                <?= $this->SiteContent->paragraphs('safety.body') ?>

            </div>

        </div>

    </div>

</section>
