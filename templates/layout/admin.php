<?php
/**
 * Admin layout
 *
 * Same shell as the dashboard (fixed sidebar + main content area), but without
 * the dashboard-only chart libraries. Used by the Users management screens so
 * the navigation stays visible when moving between them.
 *
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?= $this->Html->charset() ?>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= $this->fetch('title') ?>
    </title>

    <?= $this->Html->meta('icon') ?>

    <!-- Base CSS -->
    <?= $this->Html->css('normalize.min') ?>

    <!-- Shared design tokens -->
    <?= $this->Html->css('variables') ?>

    <!-- Shell: sidebar + main content -->
    <?= $this->Html->css('dashboard') ?>
    <?= $this->Html->css('sidebar') ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@1.32.0/dist/umd/lucide.min.js"
        integrity="sha384-tiWC3xExiSLdtFfMgETUmXUaC8RevUW8QlVQIH7p2XzwWAeYgnGiqSKD3KOLS7qk"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
</head>

<body>

<div class="dashboard-container">

    <?= $this->element('sidebar') ?>

    <main class="main-content">

        <?= $this->Flash->render() ?>

        <?= $this->fetch('content') ?>

    </main>

</div>

<!-- Page-specific scripts -->
<?= $this->fetch('script') ?>

<script>
    if (window.lucide) {
        lucide.createIcons();
    }
</script>

</body>

</html>
