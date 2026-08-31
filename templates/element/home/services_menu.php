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

                <?php
                $count = count($services);

                // The cheapest treatment reads as "from $X".
                $prices = array_filter(array_map(
                    fn ($service) => $service->price_cents,
                    $services
                ), fn ($cents) => $cents !== null);
                ?>

                <article class="services-overview-card">

                    <h3><?= h($category) ?></h3>

                    <p class="services-overview-count">
                        <?= $count ?> treatment<?= $count === 1 ? '' : 's' ?>
                        <?php if ($prices !== []): ?>
                            &middot; from AU$<?= h(number_format(min($prices) / 100, 2)) ?>
                        <?php endif; ?>
                    </p>

                </article>

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
