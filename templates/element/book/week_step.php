<?php
/**
 * One of the two arrows either side of the week name.
 *
 * A link when there is a month to load, a disabled marker when there is
 * not. The marker is still rendered so the week name keeps its place, and
 * so the script has something to turn back on if a loaded week lies that
 * way after all.
 *
 * @var \App\View\AppView $this
 * @var string $direction "back" or "forward".
 * @var string|null $to The date to load, or null if there is nowhere to go.
 * @var string $glyph
 * @var string $label What a screen reader should hear.
 * @var array<int|string, mixed> $url The page this arrow belongs to.
 */
?>

<?php if ($to !== null): ?>

    <?= $this->Html->link(
        '<span aria-hidden="true">' . $glyph . '</span>'
        . '<span class="visually-hidden">' . h($label) . '</span>',
        $url + ['?' => ['from' => $to]],
        [
            'class' => 'book-week-step',
            'escape' => false,
            'data-week-step' => $direction,
        ]
    ) ?>

<?php else: ?>

    <span
        class="book-week-step is-disabled"
        data-week-step="<?= h($direction) ?>"
        aria-hidden="true"
    ><?= $glyph ?></span>

<?php endif; ?>
