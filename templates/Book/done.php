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

            <?php $manageUrl = trim($this->SiteContent->raw('booking.manage_url')); ?>

            <p>
                <?php if ($manageUrl !== ''): ?>
                    You can
                    <a href="<?= h($manageUrl) ?>" target="_blank" rel="noopener">manage this booking</a>
                    with your mobile number - you will be texted a code.
                <?php else: ?>
                    Your confirmation email has a link for changing or
                    cancelling this appointment.
                <?php endif; ?>
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
