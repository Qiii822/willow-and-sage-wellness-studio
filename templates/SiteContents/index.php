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
$this->Html->script('site_contents', ['block' => true]);
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

    <?php // enctype is required or the browser sends the file's name only. ?>
    <?= $this->Form->create(null, [
        'class' => 'content-form',
        'url' => ['action' => 'index'],
        'type' => 'file',
    ]) ?>

    <section class="content-card">

        <h2>Page layout</h2>
        <p class="content-card-lead">
            Move blocks up and down, hide the ones you do not need, and pick
            how each is arranged. The footer always stays at the bottom.
        </p>

        <ul class="section-list" data-section-list>

            <?php foreach ($sections as $section): ?>

                <li class="section-row<?= $section['visible'] ? '' : ' is-hidden' ?>">

                    <?php /*
                     * Order is read from the sequence these arrive in, so the
                     * script only has to move the row - no numbers to keep in
                     * step.
                     */ ?>
                    <?= $this->Form->hidden('section_order[]', [
                        'value' => $section['key'],
                    ]) ?>

                    <div class="section-move">
                        <button type="button" class="section-move-button" data-move="up"
                                aria-label="Move <?= h($section['label']) ?> up">&uarr;</button>
                        <button type="button" class="section-move-button" data-move="down"
                                aria-label="Move <?= h($section['label']) ?> down">&darr;</button>
                    </div>

                    <div class="section-detail">
                        <strong><?= h($section['label']) ?></strong>
                        <span><?= h($section['description']) ?></span>
                    </div>

                    <div class="section-controls">

                        <label class="section-variant">
                            <span class="visually-hidden">
                                Arrangement for <?= h($section['label']) ?>
                            </span>
                            <?= $this->Form->select(
                                'section_variant[' . $section['key'] . ']',
                                $section['variants'],
                                [
                                    'value' => $section['variant'],
                                    'class' => 'content-input section-select',
                                ]
                            ) ?>
                        </label>

                        <label class="section-visible">
                            <?= $this->Form->checkbox(
                                'section_visible[' . $section['key'] . ']',
                                [
                                    'checked' => $section['visible'],
                                    'hiddenField' => false,
                                ]
                            ) ?>
                            Show
                        </label>

                    </div>

                </li>

            <?php endforeach; ?>

        </ul>

    </section>

    <?php foreach ($groups as $groupName => $fields): ?>

        <section class="content-card">

            <h2><?= h($groupName) ?></h2>

            <?php foreach ($fields as $key => $field): ?>

                <div class="content-field">

                    <label for="field-<?= h(str_replace('.', '-', $key)) ?>">
                        <?= h($field['label']) ?>
                    </label>

                    <?php if ($field['type'] === SiteContentsTable::TYPE_IMAGE): ?>

                        <?php $currentUrl = $this->SiteContent->heroImageUrl(); ?>

                        <?php /*
                         * Always rendered, so the script has somewhere to put
                         * a preview even when no photo is set yet.
                         */ ?>
                        <div
                            class="content-image-preview"
                            data-preview-for="hero_image"
                            <?= $currentUrl ? '' : 'hidden' ?>
                        >
                            <img
                                src="<?= h((string)$currentUrl) ?>"
                                alt="Background photo"
                                data-original-src="<?= h((string)$currentUrl) ?>"
                            >
                            <p class="content-image-note" hidden>
                                Preview of the photo you chose. It replaces the
                                current one when you save.
                            </p>
                        </div>

                        <?= $this->Form->file('hero_image', [
                            'id' => 'field-' . str_replace('.', '-', $key),
                            'class' => 'content-file',
                            'accept' => 'image/jpeg,image/png,image/webp',
                            'data-preview' => 'hero_image',
                        ]) ?>

                        <?php // Upload problems belong next to the file field. ?>
                        <div class="content-field-flash">
                            <?= $this->Flash->render('heroImage') ?>
                        </div>

                        <?php if (($values[$key] ?? '') !== ''): ?>
                            <label class="content-checkbox">
                                <?= $this->Form->checkbox('hero_image_remove', ['hiddenField' => false]) ?>
                                Remove the uploaded photo and go back to the original
                            </label>
                        <?php endif; ?>

                    <?php elseif ($field['type'] === SiteContentsTable::TYPE_TEXTAREA): ?>

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
