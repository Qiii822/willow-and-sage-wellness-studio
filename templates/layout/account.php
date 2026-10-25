<?php

/**
 * Account layout
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

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <?= $this->Html->css('normalize.min') ?>
    <?= $this->Html->css('milligram.min') ?>
    <?= $this->Html->css('cake') ?>

    <?= $this->Html->css('variables') ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->Html->css('site_dialog') ?>

    <script src="https://unpkg.com/lucide@1.32.0/dist/umd/lucide.min.js"
        integrity="sha384-tiWC3xExiSLdtFfMgETUmXUaC8RevUW8QlVQIH7p2XzwWAeYgnGiqSKD3KOLS7qk"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
</head>

<body>

<?= $this->Flash->render() ?>

<?= $this->fetch('content') ?>

<?= $this->Html->script('site_dialog') ?>
<?= $this->fetch('script') ?>

</body>
</html>
