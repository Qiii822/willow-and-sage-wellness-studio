<?php
/**
 * Public site footer.
 *
 * @var \App\View\AppView $this
 */

$phone = $this->SiteContent->raw('footer.phone');
$email = $this->SiteContent->raw('footer.email');
?>

<footer class="site-footer">

    <div class="site-footer-inner">

        <div class="site-footer-brand">
            <strong><?= $this->SiteContent->get('footer.name') ?></strong>
            <span><?= $this->SiteContent->get('footer.location') ?></span>
        </div>

        <div class="site-footer-contact">

            <span><?= $this->SiteContent->get('footer.hours') ?></span>

            <?php if ($phone !== ''): ?>
                <?php // Spaces and brackets are for reading, not for dialling. ?>
                <a href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone)) ?>">
                    <?= h($phone) ?>
                </a>
            <?php endif; ?>

            <?php if ($email !== ''): ?>
                <a href="mailto:<?= h($email) ?>"><?= h($email) ?></a>
            <?php endif; ?>

        </div>

    </div>

</footer>
