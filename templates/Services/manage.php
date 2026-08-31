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
