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

    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css('normalize.min') ?>
    <?= $this->Html->css('milligram.min') ?>
    <?= $this->Html->css('cake') ?>

    <?= $this->Html->css('variables') ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>

    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>

<?= $this->Flash->render() ?>

<?= $this->fetch('content') ?>

<?= $this->fetch('script') ?>

</body>
</html>
