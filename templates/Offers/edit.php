<?php
/**
 * Fill in the half of an offer that Square has no room for.
 *
 * There is no adding here any more. Which offers exist is Square's, so this
 * form only ever edits one that a sync has already brought across - and only
 * the fields a Square discount cannot carry.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Offer $offer
 * @var array<string, array<string, string>> $treatments Pickable treatments,
 *  grouped by category.
 */

$this->assign('title', 'Edit Offer');

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
            <h1>Edit offer</h1>
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

        <?php /*
         * The name and the percentage come from the Square discount, and the
         * sync writes both back over anything typed here. They are shown
         * read-only rather than hidden, so the card can still be read in full
         * on one screen.
         */ ?>
        <p class="content-notice">
            <strong>This offer comes from Square.</strong>
            Its name and discount are set on the discount in Square and are
            replaced every time a sync runs. Everything else below is yours to
            write.
        </p>

        <div class="content-field">
            <label for="offer-title">Offer name</label>
            <?= $this->Form->text('title', [
                'id' => 'offer-title',
                'class' => 'content-input',
                'maxlength' => 150,
                'readonly' => true,
            ]) ?>
            <p class="content-help">Change this in Square.</p>
            <?= $this->Form->error('title') ?>
        </div>

        <?php /*
         * Square cannot say what a discount is for - that lives on a pricing
         * rule most accounts never create - so it is chosen here. It is what
         * makes the offer card a way into booking, and what lets the menu
         * price that treatment with the discount already taken off.
         */ ?>
        <div class="content-field">
            <label for="offer-service">Which treatment is this for?</label>
            <?= $this->Form->select('service_id', $treatments, [
                'id' => 'offer-service',
                'class' => 'content-input',
                'empty' => 'No single treatment',
            ]) ?>
            <p class="content-help">
                Pick one and the offer card becomes a link straight into
                booking it, and the treatment is priced with the discount
                already taken off wherever it is listed. Leave it on "No
                single treatment" for a general offer like 10% off everything
                in June - the card then just says what it says.
            </p>
            <?= $this->Form->error('service_id') ?>
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

        <?php /*
         * Both prices work themselves out from the chosen treatment and the
         * Square discount, so these two boxes are an override rather than
         * something to fill in. Said here once, above both, instead of twice
         * underneath them.
         */ ?>
        <?php if ($offer->service_id && $offer->price_before_label !== null): ?>
            <p class="content-help">
                Leave both prices blank and this offer shows
                <strong><?= h((string)$offer->price_before_label) ?></strong>
                crossed out, then
                <strong><?= h((string)$offer->price_after_label) ?></strong> -
                worked out from the treatment's price in Square and the
                discount. Fill them in only for an offer that is not simply
                that treatment at a discount.
            </p>
        <?php endif; ?>

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
                'max' => 100,
                'placeholder' => '20',
                'readonly' => true,
            ]) ?>
            <p class="content-help">
                Taken from the discount in Square. Change it there.
                <?php if ($offer->discount_amount_cents !== null): ?>
                    Square has this one as a fixed amount off, which shows on
                    the card as
                    <strong><?= h($offer->discount_label ?? '') ?></strong>.
                <?php endif; ?>
                Fill in both prices above and the badge is worked out from
                them instead, so the two cannot disagree.
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

        <h2>Save your changes</h2>

        <p class="content-side-note">
            It shows on the home page once saved, if "Show on the home
            page" is ticked.
        </p>

        <?= $this->Form->button('Save offer', [
            'class' => 'content-button',
        ]) ?>

    </aside>

    </div>

    <?= $this->Form->end() ?>

</div>
