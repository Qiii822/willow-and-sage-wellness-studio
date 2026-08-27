<?php
/**
 * Home page content editor
 *
 * @var \App\View\AppView $this
 * @var array<string, string> $values
 * @var array<string, array<string, array<string, mixed>>> $groups
 */

use App\Model\Table\SiteContentsTable;

$this->assign('title', 'Website Content');

$this->Html->css('site_contents', ['block' => true]);
?>

<div class="content-page">

    <div class="content-header">

        <div>
            <h1>Website content</h1>
            <p>
                Change the words on the public home page. Everything here is
                plain text - it appears exactly as you type it.
            </p>
        </div>

        <?= $this->Html->link(
            'View home page',
            ['controller' => 'Home', 'action' => 'index'],
            [
                'class' => 'content-button secondary',
                'target' => '_blank',
                'rel' => 'noopener',
            ]
        ) ?>

    </div>

    <?= $this->Form->create(null, [
        'class' => 'content-form',
        'url' => ['action' => 'index'],
    ]) ?>

    <?php foreach ($groups as $groupName => $fields): ?>

        <section class="content-card">

            <h2><?= h($groupName) ?></h2>

            <?php foreach ($fields as $key => $field): ?>

                <div class="content-field">

                    <label for="field-<?= h(str_replace('.', '-', $key)) ?>">
                        <?= h($field['label']) ?>
                    </label>

                    <?php if ($field['type'] === SiteContentsTable::TYPE_TEXTAREA): ?>

                        <?= $this->Form->textarea($key, [
                            'id' => 'field-' . str_replace('.', '-', $key),
                            'class' => 'content-input',
                            'rows' => 3,
                            'value' => $values[$key] ?? '',
                        ]) ?>

                    <?php else: ?>

                        <?= $this->Form->text($key, [
                            'id' => 'field-' . str_replace('.', '-', $key),
                            'class' => 'content-input',
                            'value' => $values[$key] ?? '',
                        ]) ?>

                    <?php endif; ?>

                    <?php if (!empty($field['help'])): ?>
                        <p class="content-help"><?= h($field['help']) ?></p>
                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </section>

    <?php endforeach; ?>

    <div class="content-actions">

        <?= $this->Form->button('Save changes', [
            'class' => 'content-button',
        ]) ?>

    </div>

    <?= $this->Form->end() ?>

    <?php /*
     * Its own form, so a stray Enter in a text field submits the edits
     * rather than wiping them.
     */ ?>
    <?= $this->Form->create(null, [
        'class' => 'content-reset',
        'url' => ['action' => 'index'],
    ]) ?>
    <?= $this->Form->hidden('reset', ['value' => 1]) ?>
    <?= $this->Form->button('Restore original wording', [
        'class' => 'content-button danger',
        'confirm' => 'This replaces every field with the original text. Continue?',
    ]) ?>
    <?= $this->Form->end() ?>

</div>
