<?php
/**
 * Offer management
 *
 * @var \App\View\AppView $this
 * @var iterable $offers
 */

$this->assign('title', 'Offers');

$this->Html->css('site_contents', ['block' => true]);
$this->Html->css('services_admin', ['block' => true]);
$this->Html->script('site_contents', ['block' => true]);

$offers = iterator_to_array($offers);
?>

<div class="content-page">

    <div class="content-header">

        <div>
            <h1>Offers</h1>
            <p>
                What is running at the moment. These show on the home page in
                the order set here, and the block disappears by itself when
                nothing is showing.
            </p>
        </div>

        <div class="services-admin-actions">
            <?= $this->Html->link('Add offer', ['action' => 'add'], [
                'class' => 'content-button',
            ]) ?>
            <?= $this->Html->link('View home page', ['controller' => 'Home', 'action' => 'index'], [
                'class' => 'content-button secondary',
                'target' => '_blank',
                'rel' => 'noopener',
            ]) ?>
        </div>

    </div>

    <?php if ($offers === []): ?>

        <section class="content-card">
            <p class="services-admin-empty">
                No offers yet. Add one and it appears on the home page straight
                away; until then the home page leaves the section out.
            </p>
        </section>

    <?php else: ?>

        <?= $this->Form->create(null, [
            'url' => ['action' => 'reorder'],
            'class' => 'content-form',
        ]) ?>

        <section class="content-card">

            <ul class="section-list" data-section-list>

                <?php foreach ($offers as $offer): ?>

                    <li class="section-row<?= $offer->visible ? '' : ' is-hidden' ?>">

                        <?= $this->Form->hidden('offer_order[]', ['value' => $offer->id]) ?>

                        <div class="section-move">
                            <button type="button" class="section-move-button" data-move="up"
                                    aria-label="Move <?= h($offer->title) ?> up">&uarr;</button>
                            <button type="button" class="section-move-button" data-move="down"
                                    aria-label="Move <?= h($offer->title) ?> down">&darr;</button>
                        </div>

                        <?php if ($offer->image): ?>
                            <img
                                class="offer-thumb"
                                src="<?= h($this->Url->image('uploads/' . $offer->image)) ?>"
                                alt=""
                            >
                        <?php endif; ?>

                        <div class="section-detail">
                            <strong><?= h($offer->title) ?></strong>
                            <span>
                                <?= $offer->image ? 'With a picture' : 'No picture' ?>
                                <?php if (!empty($offer->terms)): ?>
                                    &middot; <?= h($offer->terms) ?>
                                <?php endif; ?>
                                <?php if (!$offer->visible): ?>
                                    &middot; hidden
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="section-controls">

                            <?= $this->Html->link('Edit', ['action' => 'edit', $offer->id], [
                                'class' => 'services-admin-link',
                            ]) ?>

                            <?= $this->Form->postLink('Delete', ['action' => 'delete', $offer->id], [
                                'class' => 'services-admin-link is-danger',
                                'confirm' => 'Remove "' . $offer->title . '"? Its picture is deleted too.',
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
