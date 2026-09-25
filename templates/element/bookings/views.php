<?php
/**
 * List or calendar - the same bookings, two ways of reading them.
 *
 * @var \App\View\AppView $this
 * @var string $current "list" or "calendar".
 */

$views = [
    'list' => ['label' => 'List', 'action' => 'index'],
    'calendar' => ['label' => 'Calendar', 'action' => 'calendar'],
];
?>

<nav class="bookings-views" aria-label="Booking views">

    <?php foreach ($views as $key => $view): ?>

        <?= $this->Html->link(
            $view['label'],
            ['controller' => 'Bookings', 'action' => $view['action']],
            [
                'class' => 'bookings-view' . ($key === $current ? ' is-current' : ''),
                'aria-current' => $key === $current ? 'page' : false,
            ]
        ) ?>

    <?php endforeach; ?>

</nav>
