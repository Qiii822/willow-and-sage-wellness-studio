<?php
/**
 * Add or edit one treatment.
 *
 * Shared by both actions: the only difference is the heading and where the
 * form posts, so keeping one file keeps the two from drifting apart.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Service $service
 * @var array<int, string> $categories
 */

$isEdit = !$service->isNew();

$this->assign('title', $isEdit ? 'Edit Treatment' : 'Add Treatment');

$this->Html->css('site_contents', ['block' => true]);
$this->Html->css('services_admin', ['block' => true]);
?>

<div class="content-page">

    <div class="content-header">
        <div>
            <h1><?= $isEdit ? 'Edit treatment' : 'Add treatment' ?></h1>
            <p>Everything here appears on the public services page.</p>
        </div>

        <?= $this->Html->link('Back to services', ['action' => 'manage'], [
            'class' => 'content-button secondary',
        ]) ?>
    </div>

    <?= $this->Form->create($service, ['class' => 'content-form']) ?>

    <section class="content-card">

        <div class="content-field">
            <label for="service-name">Treatment name</label>
            <?= $this->Form->text('name', [
                'id' => 'service-name',
                'class' => 'content-input',
                'required' => true,
                'maxlength' => 150,
            ]) ?>
            <?= $this->Form->error('name') ?>
        </div>

        <?php
        /*
         * The treatment's own category is offered even when it is not on
         * the list, so editing a row whose category has since been removed
         * does not silently reassign it on save.
         */
        $current = (string)($service->category ?? '');
        $options = $categories;

        if ($current !== '' && !in_array($current, $options, true)) {
            $options[] = $current;
        }

        sort($options);
        ?>

        <div class="content-field">

            <label for="service-category">Category</label>

            <?php if ($options === []): ?>

                <?php /*
                 * Nothing to pick yet. A free text box here would let one
                 * treatment invent a heading nothing else can find, so the
                 * form sends them to the one place categories are made.
                 */ ?>
                <p class="content-empty-field">
                    No categories yet.
                    <?= $this->Html->link(
                        'Add one on the services page',
                        ['action' => 'manage'],
                    ) ?>
                    first, then come back.
                </p>

            <?php else: ?>

                <?= $this->Form->select('category', array_combine($options, $options), [
                    'id' => 'service-category',
                    'class' => 'content-input',
                    'required' => true,
                    'value' => $current,
                    'empty' => $current === '' ? 'Choose a category' : false,
                ]) ?>

                <p class="content-help">
                    Treatments are grouped under this heading. Categories are
                    managed on the services page.
                </p>

            <?php endif; ?>

            <?= $this->Form->error('category') ?>

        </div>

        <div class="content-field">
            <label for="service-description">Description</label>
            <?= $this->Form->textarea('description', [
                'id' => 'service-description',
                'class' => 'content-input',
                'rows' => 4,
            ]) ?>
            <?= $this->Form->error('description') ?>
        </div>

        <div class="services-admin-row">

            <div class="content-field">
                <label for="service-price">Price</label>
                <?= $this->Form->text('price', [
                    'id' => 'service-price',
                    'class' => 'content-input',
                    'inputmode' => 'decimal',
                    'placeholder' => '170.00',
                    'value' => $service->price_cents === null
                        ? ''
                        : number_format($service->price_cents / 100, 2, '.', ''),
                ]) ?>
                <p class="content-help">In dollars. Leave blank to show "Price varies".</p>
                <?= $this->Form->error('price_cents') ?>
            </div>

            <div class="content-field">
                <label for="service-duration">Duration</label>
                <?= $this->Form->number('duration_minutes', [
                    'id' => 'service-duration',
                    'class' => 'content-input',
                    'min' => 1,
                    'placeholder' => '135',
                ]) ?>
                <p class="content-help">In minutes. 135 shows as "2 hr 15 min".</p>
                <?= $this->Form->error('duration_minutes') ?>
            </div>

        </div>

        <label class="section-visible">
            <?= $this->Form->checkbox('visible', [
                'checked' => $service->isNew() ? true : (bool)$service->visible,
                'hiddenField' => false,
            ]) ?>
            Show on the services page
        </label>

    </section>

    <div class="content-actions">
        <?= $this->Form->button($isEdit ? 'Save treatment' : 'Add treatment', [
            'class' => 'content-button',
        ]) ?>
    </div>

    <?= $this->Form->end() ?>

</div>
