<?php
/**
 * What this number has booked.
 *
 * @var \App\View\AppView $this
 * @var string $phone
 * @var array<int, array<string, mixed>> $bookings
 * @var string $timezone
 */

$this->assign('title', 'My bookings - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);

$zone = new DateTimeZone($timezone);
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page">

        <header class="book-header">
            <h1>My bookings</h1>
            <p>
                Everything booked with <strong><?= h($phone) ?></strong>.
                All times are
                <?= h(str_replace('_', ' ', explode('/', $timezone)[1] ?? $timezone)) ?>
                time, where the studio is.
            </p>
        </header>

        <?php if ($bookings === []): ?>

            <p class="book-empty">
                Nothing booked with that number yet.
                <?= $this->Html->link('Book a treatment', [
                    'controller' => 'Book',
                    'action' => 'index',
                ]) ?>.
            </p>

        <?php else: ?>

            <?php foreach ($bookings as $booking): ?>

                <?php
                /*
                 * Already a correct instant, carrying the server's timezone
                 * rather than the studio's - so it is converted, never
                 * relabelled, or an Adelaide appointment would read as the
                 * Melbourne clock the server happens to keep.
                 */
                $local = $booking['start_at']->setTimezone($zone);
                ?>

                <article class="mybooking">

                    <div class="mybooking-when">
                        <p class="mybooking-date"><?= h($local->format('l j F')) ?></p>
                        <p class="mybooking-time"><?= h($local->format('g:i a')) ?></p>
                    </div>

                    <div class="mybooking-what">

                        <ul class="mybooking-treatments">
                            <?php foreach ($booking['treatments'] as $treatment): ?>
                                <li><?= h($treatment) ?></li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="mybooking-actions">

                            <?php /*
                             * Only a booking made here can be moved: Square
                             * needs the treatment's duration to search for a
                             * free slot, and one taken over the phone has no
                             * draft holding it.
                             */ ?>
                            <?php if ($booking['draft'] !== null): ?>
                                <?= $this->Html->link('Change time', [
                                    'action' => 'reschedule',
                                    $booking['square_booking_id'],
                                ], ['class' => 'book-secondary']) ?>
                            <?php endif; ?>

                            <?= $this->Form->postLink('Cancel', [
                                'action' => 'cancel',
                                $booking['square_booking_id'],
                            ], [
                                'class' => 'mybooking-cancel',
                                'confirm' => 'Cancel this appointment?',
                            ]) ?>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

        <p class="book-back">
            <?= $this->Html->link('Sign out', ['action' => 'signOut']) ?>
        </p>

    </section>

</main>
