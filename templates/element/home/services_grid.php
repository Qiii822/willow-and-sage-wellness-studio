<?php
/**
 * Home page block: the three treatments.
 *
 * @var \App\View\AppView $this
 * @var string $variant Arrangement chosen in the editor.
 */

/*
 * Icons stay in the template. They are part of the design rather than copy,
 * and choosing one is not something the editor should have to reason about.
 */
$icons = ['heart', 'sparkles', 'flower'];
?>

<section class="feature-strip">

    <div class="feature-strip-inner">

        <div class="feature-grid feature-grid-<?= h($variant) ?>">

            <?php foreach ($icons as $index => $icon): ?>
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
