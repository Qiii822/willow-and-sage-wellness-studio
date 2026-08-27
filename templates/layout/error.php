<?php
/**
 * Error layout
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

    <?= $this->Html->css([
        'normalize.min',
        'milligram.min',
        'fonts',
        'cake',
        'variables'
    ]) ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
</head>

<body>

<?= $this->Flash->render() ?>

<?= $this->fetch('content') ?>

<?= $this->fetch('script') ?>

</body>

</html>
