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

<div class="content-page content-page-with-side">

    <?= $this->element('website_tabs') ?>

    <div class="content-header">

        <div>
            <h1>Offers</h1>
            <p>
                What is running at the moment. These show on the home page in
                the order set here, and the block disappears by itself when
                nothing is showing.
            </p>

            <?php /*
             * Said plainly and up front, because the alternative is someone
             * hunting for an Add button that is not there, or renaming an
             * offer and finding Square's name back after the next sync with
             * nothing to explain why.
             */ ?>
            <p class="content-notice">
                <strong>Offers come from Square.</strong>
                Each one is a discount set up in Square, and its name and
                percentage are replaced by Square's every time a sync runs.
                Add a discount in Square and it appears here; delete it there
                and it goes. A new one arrives hidden, so nothing reaches the
                home page until you say so.
            </p>

            <?php /*
             * Square keeps none of this, so it has to be written here or the
             * card is a heading and a badge. Worth saying beside the warning
             * above, which otherwise reads as "nothing here is yours".
             */ ?>
            <p class="content-notice">
                <strong>The rest of the card is yours.</strong>
                Square has nowhere to keep a description, small print, a
                picture or a before-and-after price, so those are written
                here with Edit and a sync never touches them.
            </p>
        </div>

        <?php /*
         * No "Add offer" any more. An offer made here had no Square discount
         * behind it and disappeared at the next sync.
         */ ?>
        <div class="services-admin-actions">
            <?= $this->Html->link('View home page', ['controller' => 'Home', 'action' => 'index'], [
                'class' => 'content-button secondary',
                'target' => '_blank',
                'rel' => 'noopener',
            ]) ?>
        </div>

    </div>

    <div class="content-layout">

    <div class="content-main">

    <?php if ($offers === []): ?>

        <section class="content-card">
            <p class="services-admin-empty">
                No offers yet. Set up a discount in Square and it arrives here
                at the next sync, hidden until you show it; until then the home
                page leaves the section out.
            </p>
        </section>

    <?php else: ?>

        <?= $this->Form->create(null, [
            'id' => 'offers-order-form',
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
                                <?php
                                // The same summary the card shows, so the two
                                // can be compared without opening the form.
                                $meta = array_filter([
                                    $offer->discount_label,
                                    $offer->has_saving
                                        ? $offer->price_before_label . ' to ' . $offer->price_after_label
                                        : $offer->price_after_label,
                                    $offer->image ? 'With a picture' : 'No picture',
                                    $offer->visible ? null : 'hidden',
                                ]);
                                ?>
                                <?= h(implode(' · ', $meta)) ?>
                            </span>
                        </div>

                        <div class="section-controls">

                            <?= $this->Html->link('Edit', ['action' => 'edit', $offer->id], [
                                'class' => 'services-admin-link',
                            ]) ?>

                            <?php /*
                             * Hide rather than Delete. The discount is still
                             * in Square, so a deleted offer comes back at the
                             * next sync - the button would be offering
                             * something it cannot keep.
                             */ ?>
                            <?= $this->Form->postLink(
                                $offer->visible ? 'Hide' : 'Show',
                                ['action' => 'toggleVisibility', $offer->id],
                                [
                                    'class' => 'services-admin-link',
                                    // Its own request, so it cannot be
                                    // swallowed by the reorder form wrapping
                                    // this list.
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
     * Beside the list and sticky, like the page content editor. The button
     * reaches the reorder form through its `form` attribute.
     */ ?>
    <aside class="content-side">

        <h2>Save the order</h2>

        <p class="content-side-note">
            <?php if ($offers === []): ?>
                Nothing to order yet. Offers arrive from Square at the next
                sync.
            <?php else: ?>
                Move offers with the arrows, then save. Edit and Hide work on
                their own and do not need saving.
            <?php endif; ?>
        </p>

        <?php if ($offers !== []): ?>
            <?= $this->Form->button('Save order', [
                'class' => 'content-button',
                'form' => 'offers-order-form',
            ]) ?>
        <?php endif; ?>

    </aside>

    </div>

</div>
