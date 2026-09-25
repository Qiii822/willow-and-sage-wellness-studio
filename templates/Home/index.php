<?php
/**
 * Customer facing home page
 *
 * The page is assembled from blocks whose order, visibility and arrangement
 * are set under Website content; the wording inside each comes from the same
 * editor. Nothing here is hard-coded except which blocks exist.
 *
 * @var \App\View\AppView $this
 * @var array<int, array<string, mixed>> $sections
 */

$this->assign('title', $this->SiteContent->get('site.title'));

$this->Html->css('landing', ['block' => true]);
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <?php foreach ($sections as $section): ?>
        <?= $this->element($section['element'], ['variant' => $section['variant']]) ?>
    <?php endforeach; ?>

</main>

<?= $this->element('site_footer') ?>
