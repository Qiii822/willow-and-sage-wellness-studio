<?php
/**
 * The booking is made.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\BookingDraft $draft
 * @var string $timezone
 */

$this->assign('title', 'Booking confirmed - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page">

        <header class="book-header book-done">

            <p class="book-done-tick" aria-hidden="true">&check;</p>

            <h1>You're booked in</h1>

            <p>
                We've sent the details to
                <strong><?= h($draft->customer_email) ?></strong>.
                We look forward to seeing you.
            </p>

        </header>

        <?= $this->element('book/summary', ['draft' => $draft, 'timezone' => $timezone]) ?>

        <div class="book-done-next">

            <h2>Getting here</h2>

            <address class="location-address">
                <?= $this->SiteContent->lines('location.address') ?>
            </address>

            <?php $phone = trim($this->SiteContent->raw('footer.phone')); ?>

            <?php if ($phone !== ''): ?>
                <p>
                    Need to change something? Call us on
                    <a href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= h($phone) ?></a>.
                </p>
            <?php endif; ?>

        </div>

        <p class="book-back">
            <?= $this->Html->link('Back to the treatment menu', [
                'controller' => 'Services',
                'action' => 'index',
            ]) ?>
        </p>

    </section>

</main>
