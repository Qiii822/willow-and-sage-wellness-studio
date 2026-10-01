<?php
/**
 * Add or edit one offer.
 *
 * Shared by both actions: the only difference is the heading and where the
 * form posts, so keeping one file keeps the two from drifting apart.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer $offer
 */

$isEdit = !$offer->isNew();

$this->assign('title', $isEdit ? 'Edit Offer' : 'Add Offer');

$this->Html->css('site_contents', ['block' => true]);
$this->Html->css('services_admin', ['block' => true]);
$this->Html->script('site_contents', ['block' => true]);

$currentImage = $offer->image
    ? $this->Url->image('uploads/' . $offer->image)
    : null;
?>

<div class="content-page content-page-with-side">

    <div class="content-header">
        <div>
            <h1><?= $isEdit ? 'Edit offer' : 'Add offer' ?></h1>
            <p>Offers appear on the home page while they are set to show.</p>
        </div>

        <?= $this->Html->link('Back to offers', ['action' => 'manage'], [
            'class' => 'content-button secondary',
        ]) ?>
    </div>

    <?= $this->Form->create($offer, [
        'class' => 'content-form',
        'type' => 'file',
    ]) ?>

    <div class="content-layout">

    <section class="content-card">

        <div class="content-field">
            <label for="offer-title">Offer name</label>
            <?= $this->Form->text('title', [
                'id' => 'offer-title',
                'class' => 'content-input',
                'required' => true,
                'maxlength' => 150,
            ]) ?>
            <?= $this->Form->error('title') ?>
        </div>

        <div class="content-field">
            <label for="offer-description">Description</label>
            <?= $this->Form->textarea('description', [
                'id' => 'offer-description',
                'class' => 'content-input',
                'rows' => 4,
            ]) ?>
            <?= $this->Form->error('description') ?>
        </div>

        <div class="services-admin-row">

            <div class="content-field">
                <label for="offer-price-before">Usual price</label>
                <?= $this->Form->text('price_before', [
                    'id' => 'offer-price-before',
                    'class' => 'content-input',
                    'inputmode' => 'decimal',
                    'placeholder' => '170.00',
                    'value' => $offer->price_before_cents === null
                        ? ''
                        : number_format($offer->price_before_cents / 100, 2, '.', ''),
                ]) ?>
                <p class="content-help">In dollars. Shown crossed out.</p>
                <?= $this->Form->error('price_before_cents') ?>
            </div>

            <div class="content-field">
                <label for="offer-price-after">Offer price</label>
                <?= $this->Form->text('price_after', [
                    'id' => 'offer-price-after',
                    'class' => 'content-input',
                    'inputmode' => 'decimal',
                    'placeholder' => '140.00',
                    'value' => $offer->price_after_cents === null
                        ? ''
                        : number_format($offer->price_after_cents / 100, 2, '.', ''),
                ]) ?>
                <p class="content-help">In dollars. What they pay now.</p>
                <?= $this->Form->error('price_after_cents') ?>
            </div>

        </div>

        <div class="content-field">
            <label for="offer-discount">Discount badge</label>
            <?= $this->Form->number('discount_percent', [
                'id' => 'offer-discount',
                'class' => 'content-input',
                'min' => 1,
                'max' => 99,
                'placeholder' => '20',
            ]) ?>
            <p class="content-help">
                A percentage, shown as "20% off". Only needed for an offer with
                no set prices, like 20% off every facial - when both prices
                above are filled in, the badge is worked out from them so the
                two cannot disagree.
            </p>
            <?= $this->Form->error('discount_percent') ?>
        </div>

        <div class="content-field">
            <label for="offer-terms">Small print</label>
            <?= $this->Form->text('terms', [
                'id' => 'offer-terms',
                'class' => 'content-input',
                'maxlength' => 255,
                'placeholder' => 'New clients only. Until 30 June.',
            ]) ?>
            <p class="content-help">
                Optional. Shown smaller, underneath the description.
            </p>
            <?= $this->Form->error('terms') ?>
        </div>

        <div class="content-field">

            <label for="offer-image">Picture</label>

            <?php /*
             * Always rendered, so the script has somewhere to put a preview
             * even when no picture is set yet.
             */ ?>
            <div
                class="content-image-preview"
                data-preview-for="image"
                <?= $currentImage ? '' : 'hidden' ?>
            >
                <img
                    src="<?= h((string)$currentImage) ?>"
                    alt="Picture for this offer"
                    data-original-src="<?= h((string)$currentImage) ?>"
                >
                <p class="content-image-note" hidden>
                    Preview of the picture you chose. It replaces the current
                    one when you save.
                </p>
            </div>

            <?= $this->Form->file('image', [
                'id' => 'offer-image',
                'class' => 'content-file',
                'accept' => 'image/jpeg,image/png,image/webp',
                'data-preview' => 'image',
            ]) ?>

            <?php // Upload problems belong next to the file field. ?>
            <div class="content-field-flash">
                <?= $this->Flash->render('offerImage') ?>
            </div>

            <p class="content-help">
                Optional. JPEG, PNG or WebP, up to 5MB.
            </p>

            <?php if ($offer->image): ?>
                <label class="content-checkbox">
                    <?= $this->Form->checkbox('image_remove', ['hiddenField' => false]) ?>
                    Remove the picture
                </label>
            <?php endif; ?>

        </div>

        <label class="section-visible">
            <?= $this->Form->checkbox('visible', [
                'checked' => $offer->isNew() ? true : (bool)$offer->visible,
                'hiddenField' => false,
            ]) ?>
            Show on the home page
        </label>

    </section>

    <?php /*
     * Beside the form and sticky, like the page content editor, so the
     * button is in reach without scrolling past the picture first.
     */ ?>
    <aside class="content-side">

        <h2><?= $isEdit ? 'Save your changes' : 'Add this offer' ?></h2>

        <p class="content-side-note">
            It shows on the home page once saved, if "Show on the home
            page" is ticked.
        </p>

        <?= $this->Form->button($isEdit ? 'Save offer' : 'Add offer', [
            'class' => 'content-button',
        ]) ?>

    </aside>

    </div>

    <?= $this->Form->end() ?>

</div>
