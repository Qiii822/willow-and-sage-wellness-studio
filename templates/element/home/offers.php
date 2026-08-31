<?php
/**
 * Home page block: the offers currently running.
 *
 * @var \App\View\AppView $this
 * @var string $variant Arrangement chosen in the editor.
 */

/*
 * Read here rather than passed in, so the block stays self-contained: it can
 * be added to the page from the editor without a controller having to know
 * it needs feeding.
 */
$offers = \Cake\ORM\TableRegistry::getTableLocator()->get('Offers')->current();

// Nothing running at the moment: the block simply does not appear.
if ($offers === []) {
    return;
}

$this->Html->css('offers', ['block' => true]);

$intro = $this->SiteContent->raw('offers.intro');
?>

<section class="feature-strip" id="offers">

    <div class="feature-strip-inner">

        <div class="section-intro<?= $intro === '' ? ' offers-heading-only' : '' ?>">

            <h2><?= $this->SiteContent->get('offers.heading') ?></h2>

            <?php if ($intro !== ''): ?>
                <p><?= $this->SiteContent->lines('offers.intro') ?></p>
            <?php endif; ?>

        </div>

        <div class="offers-list offers-list-<?= h($variant) ?>">

            <?php foreach ($offers as $offer): ?>

                <article class="offer-card">

                    <?php if ($offer->image): ?>
                        <?php /*
                         * Decorative: the offer's name and description carry
                         * the meaning, so an alt text here would only repeat
                         * the heading directly beneath it.
                         */ ?>
                        <div class="offer-image">
                            <img
                                src="<?= h($this->Url->image('uploads/' . $offer->image)) ?>"
                                alt=""
                                loading="lazy"
                            >
                        </div>
                    <?php endif; ?>

                    <div class="offer-body">

                        <h3><?= h($offer->title) ?></h3>

                        <?php if (!empty($offer->description)): ?>
                            <p class="offer-description">
                                <?= nl2br(h($offer->description), false) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($offer->terms)): ?>
                            <p class="offer-terms"><?= h($offer->terms) ?></p>
                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    </div>

</section>
