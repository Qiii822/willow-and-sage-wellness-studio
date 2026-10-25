<?php
/**
 * Home page block: a short overview of the treatment menu.
 *
 * Shows the categories rather than every treatment, so the home page stays
 * readable; the full list lives on the services page.
 *
 * @var \App\View\AppView $this
 * @var string $variant Arrangement chosen in the editor.
 */

/*
 * Read here rather than passed in, so the block stays self-contained: it can
 * be added to the page from the editor without a controller having to know
 * it needs feeding.
 */
$menu = \Cake\ORM\TableRegistry::getTableLocator()->get('Services')->menu();

// Nothing entered yet: the block simply does not appear.
if ($menu === []) {
    return;
}

$this->Html->css('services', ['block' => true]);
?>

<section class="feature-strip">

    <div class="feature-strip-inner">

        <div class="section-intro services-overview-heading">
            <h2><?= $this->SiteContent->get('services.menu_heading') ?></h2>
        </div>

        <div class="services-overview services-overview-<?= h($variant) ?>">

            <?php foreach ($menu as $category => $services): ?>

                <?php $count = count($services); ?>
                <?php $slug = \App\Model\Table\ServicesTable::categorySlug($category); ?>

                <?php /*
                 * Straight to this category's treatments: the menu filtered
                 * to it, and scrolled past the page's header to the list
                 * itself, which the services page then highlights.
                 */ ?>
                <a
                    class="services-overview-card"
                    href="<?= h($this->Url->build([
                        'controller' => 'Services',
                        'action' => 'index',
                        '?' => ['category' => $slug],
                        '#' => 'cat-' . $slug,
                    ])) ?>"
                >

                    <h3><?= h($category) ?></h3>

                    <?php /*
                     * Deliberately no "from $X" here. The cheapest row in a
                     * category is often an add-on - $15 for hot stones on top
                     * of a massage - so the lowest price is not the price of
                     * anything you can actually book on its own, and reading
                     * "Massage from $15" next to an $85 massage feels like a
                     * bait. Prices live on the full menu, where each one sits
                     * beside what it actually buys.
                     */ ?>
                    <?php /*
                     * Worded and underlined as a link, so the card reads as
                     * somewhere to go rather than a label - the whole card is
                     * the link, but nothing else about it says so.
                     */ ?>
                    <p class="services-overview-link">
                        View <?= $count ?> treatment<?= $count === 1 ? '' : 's' ?>
                        <span aria-hidden="true">&rarr;</span>
                    </p>

                </a>

            <?php endforeach; ?>

        </div>

        <div class="services-overview-action">
            <?= $this->Html->link(
                'See the full service menu',
                ['controller' => 'Services', 'action' => 'index'],
                ['class' => 'hero-cta']
            ) ?>
        </div>

    </div>

</section>
