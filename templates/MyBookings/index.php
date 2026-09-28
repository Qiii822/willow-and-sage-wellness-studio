<?php
/**
 * Where to go to change a booking.
 *
 * This page used to ask for a mobile number and text back a code. Nothing
 * sends that text - there is no SMS service - so in production the customer
 * would have entered their number and waited for a message that never came.
 *
 * Square already solves this: its own confirmation carries a link for
 * changing or cancelling, which is how the studio's existing site works.
 * That link is made inside Square's message and is not exposed by the API,
 * so it cannot be repeated here - this page points at it instead.
 *
 * The phone sign-in behind it is still in the code, unreached, for whenever
 * a way to actually send the code exists.
 *
 * @var \App\View\AppView $this
 */

$this->assign('title', 'My bookings - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);

$phone = trim($this->SiteContent->raw('footer.phone'));
$manageUrl = trim($this->SiteContent->raw('booking.manage_url'));
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page book-page-narrow">

        <header class="book-header">
            <h1>My bookings</h1>
            <p>
                There is no account to remember - your mobile number is all
                you need.
            </p>
        </header>

        <div class="book-summary">

            <h2>Changing or cancelling</h2>

            <?php if ($manageUrl !== ''): ?>

                <p class="book-summary-note">
                    Enter your mobile number on the page below and you will be
                    texted a code. From there you can move an appointment to
                    another time or cancel it.
                </p>

                <?php /*
                 * Off to Square. Square has no API for this - a booking
                 * carries no link of its own, and nothing will send a code
                 * on our behalf - but its own page does the whole job, at
                 * no cost and with nothing for us to run.
                 *
                 * A new tab, because it is somebody else's site and the
                 * customer should still have ours behind them.
                 */ ?>
                <p class="book-manage-action">
                    <a
                        class="hero-cta"
                        href="<?= h($manageUrl) ?>"
                        target="_blank"
                        rel="noopener"
                    >Manage my booking</a>
                </p>

            <?php else: ?>

                <p class="book-summary-note">
                    Your confirmation email has a link for changing or
                    cancelling your appointment.
                </p>

            <?php endif; ?>

            <?php if ($phone !== ''): ?>
                <p class="book-summary-note">
                    Booked over the phone, or having trouble? Call us on
                    <a href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= h($phone) ?></a>
                    and we will sort it out.
                </p>
            <?php endif; ?>

        </div>

        <p class="book-back">
            <?= $this->Html->link('Book a treatment', [
                'controller' => 'Book',
                'action' => 'index',
            ]) ?>
        </p>

    </section>

</main>
