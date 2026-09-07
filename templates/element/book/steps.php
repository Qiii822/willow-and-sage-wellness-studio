<?php
/**
 * Where the customer is in the booking flow.
 *
 * @var \App\View\AppView $this
 * @var int $current Which step is in progress, 1-4.
 */

$steps = [
    1 => 'Treatments',
    2 => 'Therapist',
    3 => 'Time',
    4 => 'Your details',
];
?>

<ol class="book-steps">

    <?php foreach ($steps as $number => $label): ?>

        <li class="book-step<?= $number === $current ? ' is-current' : '' ?><?= $number < $current ? ' is-done' : '' ?>">

            <span class="book-step-number" aria-hidden="true"><?= $number ?></span>

            <span class="book-step-label">
                <?= h($label) ?>
                <?php if ($number === $current): ?>
                    <span class="visually-hidden">(current step)</span>
                <?php endif; ?>
            </span>

        </li>

    <?php endforeach; ?>

</ol>
