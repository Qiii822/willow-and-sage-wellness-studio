<?php
/**
 * Default layout
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

    <?= $this->Html->css([
        'normalize.min',
        'milligram.min',
        'fonts',
        'cake',
        'variables'
    ]) ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>

    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>

<main class="main">
    <div class="container">

        <?= $this->Flash->render() ?>

        <?= $this->fetch('content') ?>

    </div>
</main>

<footer>
</footer>

<script>
    if (window.lucide) {
        lucide.createIcons();
    }
</script>

</body>

</html>
