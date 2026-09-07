<?php
/**
 * Pick a new time for an existing appointment.
 *
 * @var \App\View\AppView $this
 * @var array<string, mixed> $booking
 * @var string $bookingId
 * @var array<string, array<int, array{start_at: string, label: string}>> $byDay
 * @var string $timezone
 */

$this->assign('title', 'Change your time - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);

// Converted, not relabelled: the stored value carries the server's own
// timezone rather than the studio's.
$current = $booking['start_at']->setTimezone(new DateTimeZone($timezone));
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page">

        <header class="book-header">
            <h1>Change your time</h1>
            <p>
                <?= h(implode(', ', $booking['treatments'])) ?>, currently
                <strong><?= h($current->format('l j F, g:i a')) ?></strong>.
                Choose a new time below and we will move it.
            </p>
        </header>

        <?php if ($byDay === []): ?>

            <p class="book-empty">
                There are no other free times in the next few weeks.
                Call the studio and we will find something.
            </p>

        <?php else: ?>

            <?= $this->Form->create(null, ['class' => 'book-form']) ?>

            <?php foreach ($byDay as $day => $slots): ?>

                <?php $date = new DateTimeImmutable($day); ?>

                <section class="book-day">

                    <h2><?= h($date->format('l j F')) ?></h2>

                    <div class="book-slots">

                        <?php foreach ($slots as $slot): ?>

                            <button
                                type="submit"
                                name="start_at"
                                value="<?= h($slot['start_at']) ?>"
                                class="book-slot"
                            ><?= h($slot['label']) ?></button>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endforeach; ?>

            <?= $this->Form->end() ?>

        <?php endif; ?>

        <p class="book-back">
            <?= $this->Html->link('Keep it as it is', ['action' => 'index']) ?>
        </p>

    </section>

</main>
