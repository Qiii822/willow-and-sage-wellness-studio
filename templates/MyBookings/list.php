<?php
/**
 * What this number has booked: what is coming up, and what has been.
 *
 * Laid out the way Square's own list of a customer's appointments is - the
 * upcoming ones to change or cancel, the past ones to book again - as cards
 * like the rest of the booking pages.
 *
 * @var \App\View\AppView $this
 * @var string $phone
 * @var array<int, array<string, mixed>> $bookings Upcoming, soonest first.
 * @var array<int, array<string, mixed>> $past Latest first.
 * @var string $timezone
 */

use App\Model\AustralianMobile;

$this->assign('title', 'My bookings - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);
$this->Html->script('local_times', ['block' => true]);

$zone = new DateTimeZone($timezone);

// "0451 663 613", the way people say their own number.
$normalised = AustralianMobile::normalise($phone);
$shownPhone = $normalised === null
    ? $phone
    : vsprintf('0%s %s %s', [substr($normalised, 3, 3), substr($normalised, 6, 3), substr($normalised, 9, 3)]);
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page mybookings-page">

        <header class="book-header mybookings-header">
            <div>
                <h1>My bookings</h1>
                <p>
                    Signed in as <strong><?= h($shownPhone) ?></strong>.
                    <span data-zone-note="<?= h($timezone) ?>">
                        All times are
                        <?= h(str_replace('_', ' ', explode('/', $timezone)[1] ?? $timezone)) ?>
                        time, where the studio is.
                    </span>
                </p>
            </div>

            <?= $this->Html->link('Sign out', ['action' => 'signOut'], ['class' => 'book-secondary']) ?>
        </header>

        <h2 class="mybooking-section">Upcoming</h2>

        <?php if ($bookings === []): ?>

            <div class="mybooking-empty">
                <p class="mybooking-empty-title">No upcoming appointments</p>
                <p>When you book a treatment, it will be waiting for you here.</p>
                <?= $this->Html->link('Book a treatment', [
                    'controller' => 'Book',
                    'action' => 'index',
                ], ['class' => 'mybooking-cta']) ?>
            </div>

        <?php else: ?>

            <div class="mybooking-list">
                <?php foreach ($bookings as $booking): ?>

                    <?php
                    $actions = [];

                    /*
                     * Only a booking made here can be moved: Square needs the
                     * treatment's duration to search for a free slot, and one
                     * taken over the phone has no draft holding it.
                     */
                    if ($booking['draft'] !== null) {
                        $actions[] = $this->Html->link('Change time', [
                            'action' => 'reschedule',
                            $booking['square_booking_id'],
                        ], ['class' => 'book-secondary']);
                    }

                    $actions[] = $this->Form->postLink('Cancel', [
                        'action' => 'cancel',
                        $booking['square_booking_id'],
                    ], [
                        'class' => 'mybooking-cancel',
                        'confirm' => 'Cancel this appointment?',
                        // The words on the site's own dialog (site_dialog.js).
                        'data-confirm-label' => 'Cancel appointment',
                        'data-cancel-label' => 'Keep it',
                        'data-confirm-danger' => true,
                    ]);
                    ?>

                    <?= $this->element('my_bookings/card', compact('booking', 'zone', 'actions')) ?>

                <?php endforeach; ?>
            </div>

            <?php /*
             * Someone checking on an appointment is often about to book the
             * next one, so the way in sits right under what they have.
             */ ?>
            <p class="mybooking-more">
                <?= $this->Html->link('Book another treatment', [
                    'controller' => 'Book',
                    'action' => 'index',
                ], ['class' => 'mybooking-cta']) ?>
            </p>

        <?php endif; ?>

        <?php if ($past !== []): ?>

            <h2 class="mybooking-section">Past</h2>

            <div class="mybooking-list">
                <?php foreach ($past as $booking): ?>

                    <?php
                    /*
                     * The same treatments, ticked and waiting in the cart.
                     * Left off when none of them is on the menu any more -
                     * there would be nothing to book.
                     */
                    $actions = $booking['rebook_service_ids'] === [] ? [] : [
                        $this->Html->link('Book again', [
                            'controller' => 'Book',
                            'action' => 'index',
                            '?' => ['service' => $booking['rebook_service_ids']],
                        ], ['class' => 'mybooking-cta is-small']),
                    ];
                    ?>

                    <?= $this->element('my_bookings/card', compact('booking', 'zone', 'actions') + ['isPast' => true]) ?>

                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </section>

</main>
