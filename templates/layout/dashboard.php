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

<link rel="icon" type="image/svg+xml" href="/favicon.svg">

<!-- Base CSS -->
<?= $this->Html->css('normalize.min') ?>

<!-- Bootstrap -->
<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
rel="stylesheet"
>

<!-- Shared design tokens -->
<?= $this->Html->css('variables') ?>

<!-- Dashboard CSS -->
<?= $this->Html->css('dashboard') ?>
<?= $this->Html->css('sidebar') ?>
<?= $this->Html->css('topbar') ?>
<?= $this->Html->css('cards') ?>
<?= $this->Html->css('table') ?>
<?= $this->Html->css('responsive') ?>

<?= $this->fetch('meta') ?>
<?= $this->fetch('css') ?>

<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@1.32.0/dist/umd/lucide.min.js"
        integrity="sha384-tiWC3xExiSLdtFfMgETUmXUaC8RevUW8QlVQIH7p2XzwWAeYgnGiqSKD3KOLS7qk"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
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

<?= $this->Html->script('dashboard') ?>
<?= $this->Html->script('recent-customers') ?>

</body>
</html>
