<?php
/**
 * Dashboard layout
 *
 * @var \App\View\AppView $this
 * @var string $content
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        <?= $this->fetch('title') ?>
    </title>

    <?= $this->Html->meta('icon') ?>

    <!-- Base CSS -->
    <?= $this->Html->css('normalize.min') ?>

    <!-- Bootstrap -->
    <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
            rel="stylesheet"
    >

    <!-- Dashboard CSS -->
    <?= $this->Html->css('dashboard') ?>
    <?= $this->Html->css('sidebar') ?>
    <?= $this->Html->css('topbar') ?>
    <?= $this->Html->css('cards') ?>
    <?= $this->Html->css('charts') ?>
    <?= $this->Html->css('table') ?>
    <?= $this->Html->css('responsive') ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<?= $this->Flash->render() ?>

<?= $this->fetch('content') ?>

<!-- Bootstrap JS -->
<script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js">
</script>

<!-- Page-specific scripts -->
<?= $this->fetch('script') ?>

<!-- Initialise Lucide icons -->
<script>
    if (window.lucide) {
        lucide.createIcons();
    }
</script>

</body>
</html>
