<?php
/**
 * Treatment menu management
 *
 * @var \App\View\AppView $this
 * @var iterable $services
 */

$this->assign('title', 'Services');

$this->Html->css('site_contents', ['block' => true]);
$this->Html->css('services_admin', ['block' => true]);
$this->Html->script('site_contents', ['block' => true]);

$services = iterator_to_array($services);
?>

<div class="content-page">

    <div class="content-header">

        <div>
            <h1>Services</h1>
            <p>
                The treatment menu shown on the public services page. Order
                here is the order customers see; categories follow whichever
                treatment appears first.
            </p>

            <?php /*
             * Said plainly and up front, because the alternative is someone
             * correcting a price, watching it save, and finding it back to
             * the old figure after the next sync with nothing to explain why.
             */ ?>
            <?php if ($syncedCount > 0): ?>
                <p class="content-notice">
                    <strong>Square is in charge of this menu.</strong>
                    Names, descriptions, prices, durations and categories are
                    replaced by Square's every time a sync runs, and a
                    treatment added here is removed. Change those in Square.
                    Whether a treatment is shown and the order it appears in
                    are kept here, because Square has no way to say either.
                </p>
            <?php endif; ?>
        </div>

        <div class="services-admin-actions">
            <?= $this->Html->link('Add treatment', ['action' => 'add'], [
                'class' => 'content-button',
            ]) ?>
            <?= $this->Html->link('View page', ['action' => 'index'], [
                'class' => 'content-button secondary',
                'target' => '_blank',
                'rel' => 'noopener',
            ]) ?>
        </div>

    </div>

    <?php /*
     * Categories live here rather than inside the treatment form. One is
     * a heading the whole menu is grouped by; inventing one halfway
     * through filling in a treatment is how a menu ends up with "Massage"
     * and "massages" side by side.
     */ ?>
    <section class="content-card category-panel">

        <h2>Categories</h2>

        <p class="services-admin-empty">
            The headings treatments are grouped under on the public page.
        </p>

        <?php /*
         * The same warning the treatment list carries, for the same reason:
         * one added here looks saved, and then quietly goes at the next
         * sync unless Square has it too.
         */ ?>
        <?php if ($syncedCount > 0): ?>
            <p class="content-notice">
                <strong>Square owns this list too.</strong>
                A sync replaces it with Square's categories, so one added
                here is removed again unless Square has it as well. Add it
                in Square to keep it.
            </p>
        <?php endif; ?>

        <?= $this->Form->create(null, [
            'url' => ['action' => 'addCategory'],
            'class' => 'category-add',
        ]) ?>

        <?= $this->Form->text('name', [
            'class' => 'content-input',
            'required' => true,
            'maxlength' => 100,
            'placeholder' => 'e.g. Massage',
            'aria-label' => 'New category name',
        ]) ?>

        <?= $this->Form->button('Add category', ['class' => 'content-button']) ?>

        <?= $this->Form->end() ?>

        <?php if ($categories !== []): ?>

            <ul class="category-list">

                <?php foreach ($categories as $category): ?>

                    <li class="category-row">

                        <span class="category-name"><?= h($category['name']) ?></span>

                        <span class="category-count">
                            <?= $category['used'] === 0
                                ? 'not used'
                                : $category['used'] . ' treatment' . ($category['used'] === 1 ? '' : 's') ?>
                        </span>

                        <?php if ($category['used'] === 0 && $category['id'] !== null): ?>
                            <?= $this->Form->postLink('Remove', ['action' => 'deleteCategory', $category['id']], [
                                'class' => 'services-admin-link is-danger',
                                'confirm' => 'Remove the category "' . $category['name'] . '"?',
                                'block' => true,
                            ]) ?>
                        <?php else: ?>
                            <?php /*
                             * No control at all rather than one that
                             * refuses: a button that never works is worse
                             * than none, and the note says why.
                             */ ?>
                            <span class="category-locked">
                                <?= $category['used'] > 0 ? 'in use' : 'from Square' ?>
                            </span>
                        <?php endif; ?>

                    </li>

                <?php endforeach; ?>

            </ul>

        <?php endif; ?>

    </section>

    <?php if ($services === []): ?>

        <section class="content-card">
            <p class="services-admin-empty">
                No treatments yet. Add the first one and it will appear on the
                services page straight away.
            </p>
        </section>

    <?php else: ?>

        <?= $this->Form->create(null, [
            'url' => ['action' => 'reorder'],
            'class' => 'content-form',
        ]) ?>

        <section class="content-card">

            <ul class="section-list" data-section-list>

                <?php foreach ($services as $service): ?>

                    <li class="section-row<?= $service->visible ? '' : ' is-hidden' ?>">

                        <?= $this->Form->hidden('service_order[]', [
                            'value' => $service->id,
                        ]) ?>

                        <div class="section-move">
                            <button type="button" class="section-move-button" data-move="up"
                                    aria-label="Move <?= h($service->name) ?> up">&uarr;</button>
                            <button type="button" class="section-move-button" data-move="down"
                                    aria-label="Move <?= h($service->name) ?> down">&darr;</button>
                        </div>

                        <div class="section-detail">
                            <strong><?= h($service->name) ?></strong>
                            <span>
                                <?= h($service->category) ?>
                                <?php
                                $meta = array_filter([
                                    $service->price_label,
                                    $service->duration_label,
                                ]);
                                ?>
                                <?php if ($meta !== []): ?>
                                    &middot; <?= h(implode(' · ', $meta)) ?>
                                <?php endif; ?>
                                <?php if (!$service->visible): ?>
                                    &middot; hidden
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="section-controls">

                            <?= $this->Html->link('Edit', ['action' => 'edit', $service->id], [
                                'class' => 'services-admin-link',
                            ]) ?>

                            <?= $this->Form->postLink('Delete', ['action' => 'delete', $service->id], [
                                'class' => 'services-admin-link is-danger',
                                'confirm' => 'Remove "' . $service->name . '" from the menu?',
                                // Its own request, so it cannot be swallowed
                                // by the reorder form wrapping this list.
                                'block' => true,
                            ]) ?>

                        </div>

                    </li>

                <?php endforeach; ?>

            </ul>

            <div class="content-actions">
                <?= $this->Form->button('Save order', ['class' => 'content-button']) ?>
            </div>

        </section>

        <?= $this->Form->end() ?>

        <?php /*
         * The delete buttons above are rendered here, outside the reorder
         * form: a form cannot legally contain another, and nesting them
         * makes the inner one silently do nothing.
         */ ?>
        <?= $this->fetch('postLink') ?>

    <?php endif; ?>

</div>
