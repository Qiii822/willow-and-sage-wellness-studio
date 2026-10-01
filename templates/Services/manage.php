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

<div class="content-page content-page-with-side">

    <?= $this->element('website_tabs') ?>

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

        <?php /*
         * No "Add treatment" any more. The menu is Square's, and one added
         * here disappeared at the next sync - the button offered something
         * the app could not keep.
         */ ?>
        <div class="services-admin-actions">
            <?= $this->Html->link('View page', ['action' => 'index'], [
                'class' => 'content-button secondary',
                'target' => '_blank',
                'rel' => 'noopener',
            ]) ?>
        </div>

    </div>

    <div class="content-layout">

    <div class="content-main">

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
         * Read-only, like the treatments. The add box and the Remove links
         * were taken out with them: a sync replaces this list with Square's,
         * so one added here vanished at the next one and one removed came
         * straight back.
         */ ?>
        <p class="content-notice">
            <strong>Categories come from Square.</strong>
            They are set on each treatment in Square, and a sync brings them
            across. There is nothing to change here.
        </p>

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

                        <span class="category-locked">
                            <?= $category['used'] > 0 ? 'in use' : 'not used' ?>
                        </span>

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
            'id' => 'services-order-form',
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

                        <?php /*
                         * Edit and Delete are gone - both changed things
                         * the next sync put straight back. Showing or
                         * hiding is the one decision left here, so it is
                         * the one control, rather than a checkbox buried in
                         * a form of fields nobody could really change.
                         */ ?>
                        <div class="section-controls">

                            <?= $this->Form->postLink(
                                $service->visible ? 'Hide' : 'Show',
                                ['action' => 'toggleVisibility', $service->id],
                                [
                                    'class' => 'services-admin-link',
                                    // Its own request, so it cannot be
                                    // swallowed by the reorder form
                                    // wrapping this list.
                                    'block' => true,
                                ]
                            ) ?>

                        </div>

                    </li>

                <?php endforeach; ?>

            </ul>

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

    <?php /*
     * Beside the list and sticky, like the page content editor, so saving
     * the order does not mean scrolling past every treatment first. The
     * button reaches the reorder form through its `form` attribute.
     */ ?>
    <aside class="content-side">

        <h2>Save the order</h2>

        <p class="content-side-note">
            <?php if ($services === []): ?>
                Nothing to order until treatments arrive from Square.
            <?php else: ?>
                Move treatments with the arrows, then save. Show and Hide
                take effect straight away.
            <?php endif; ?>
        </p>

        <?php if ($services !== []): ?>
            <?= $this->Form->button('Save order', [
                'class' => 'content-button',
                'form' => 'services-order-form',
            ]) ?>
        <?php endif; ?>

    </aside>

    </div>

</div>
