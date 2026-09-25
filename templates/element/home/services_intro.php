<?php
/**
 * Home page block: heading and paragraph about the studio.
 *
 * @var \App\View\AppView $this
 * @var string $variant Arrangement chosen in the editor.
 */
?>

<section class="feature-strip">

    <div class="feature-strip-inner">

        <div class="section-intro section-align-<?= h($variant) ?>">

            <h2><?= $this->SiteContent->get('services.heading') ?></h2>

            <p><?= $this->SiteContent->get('services.intro') ?></p>

        </div>

    </div>

</section>
