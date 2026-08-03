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

    <?= $this->Html->css('normalize.min') ?>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <?= $this->Html->css('dashboard') ?>


    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>

    <script src="https://unpkg.com/lucide@latest"></script>

</head>

<body>
<?= $this->Flash->render() ?>

<?= $this->fetch('content') ?>

<?= $this->fetch('script') ?>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js">
</script>

<script>
    lucide.createIcons();
</script>

</body>
</html>
