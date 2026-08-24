<?php
/**
 * Public site layout
 *
 * Used by the customer facing pages. Deliberately does not load the
 * dashboard/milligram styles so the landing design stands on its own.
 *
 * @var \App\View\AppView $this
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

<!-- Shared design tokens -->
<?= $this->Html->css('variables') ?>

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

<?= $this->fetch('script') ?>

<script>
    if (window.lucide) {
        lucide.createIcons();
    }
</script>

</body>

</html>
