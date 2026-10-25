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
<?= $this->Html->css('site_dialog') ?>

<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@1.32.0/dist/umd/lucide.min.js"
        integrity="sha384-tiWC3xExiSLdtFfMgETUmXUaC8RevUW8QlVQIH7p2XzwWAeYgnGiqSKD3KOLS7qk"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
</head>

<body>

<?php /*
 * Flash messages are not rendered here. Standing at the top of the body
 * they landed above the studio's name, outside the page's own width and
 * with none of its styling. Every page on this layout opens with the
 * site_header element, so they are rendered there instead - directly under
 * the header, inside the page.
 */ ?>
<?= $this->fetch('content') ?>

<?= $this->Html->script('site_dialog') ?>
<?= $this->fetch('script') ?>

<script>
    if (window.lucide) {
        lucide.createIcons();
    }
</script>

</body>

</html>
