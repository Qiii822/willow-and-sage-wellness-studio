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

            <?php /*
             * "We've sent" was not true: this site sends no email at all.
             * The confirmation comes from Square, which is also what
             * carries the link for changing or cancelling - Square makes
             * that link inside its own message and does not expose it to
             * us, so it cannot be repeated here.
             */ ?>
            <p>
                Your confirmation is on its way to
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

            <h2>Changing or cancelling</h2>

            <?php /*
             * Back to our own page rather than off to Square's. Nothing is
             * needed but the number this booking was made with, which the
             * customer has just typed - so there is nothing to look up in an
             * email and no second site to arrive at.
             */ ?>
            <p>
                Go to
                <?= $this->Html->link('My bookings', [
                    'controller' => 'MyBookings',
                    'action' => 'index',
                ]) ?>
                and enter <strong><?= h($draft->customer_phone) ?></strong> -
                we will text you a code, and from there you can move this
                appointment or cancel it.
                <?php if ($phone !== ''): ?>
                    Or call us on
                    <a href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= h($phone) ?></a>.
                <?php endif; ?>
            </p>

        </div>

        <p class="book-back">
            <?= $this->Html->link('Back to the treatment menu', [
                'controller' => 'Services',
                'action' => 'index',
            ]) ?>
        </p>

    </section>

</main>
