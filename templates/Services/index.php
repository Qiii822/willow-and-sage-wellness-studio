<?php
/**
 * Public treatment menu
 *
 * @var \App\View\AppView $this
 * @var array<string, array<int, \App\Model\Entity\Service>> $menu
 */

$this->assign('title', 'Services - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->script('services', ['block' => true]);

$bookingUrl = $this->SiteContent->raw('booking.url');
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="services-page">

        <header class="services-header">
            <p class="services-eyebrow"><?= $this->SiteContent->get('services.heading') ?></p>
            <h1><?= $this->SiteContent->get('services.menu_heading') ?></h1>
            <p class="services-lead"><?= $this->SiteContent->get('services.intro') ?></p>

            <?php /*
             * Repeated at the top as well as the foot of the page. The menu
             * runs long enough that someone who already knows what they want
             * should not have to scroll past all of it to reach Square.
             */ ?>
            <?php if ($menu !== []): ?>
                <p class="services-header-action">
                    <a
                        class="hero-cta"
                        href="<?= h($bookingUrl) ?>"
                        target="_blank"
                        rel="noopener"
                    >
                        Book on Square
                    </a>
                </p>
            <?php endif; ?>
        </header>

        <?php if ($menu === []): ?>

            <p class="services-empty">
                The treatment list is being updated. Please use the button
                below to see everything available and book.
            </p>

        <?php else: ?>

            <?php /*
             * Real links, so the filter works with scripting off and a
             * narrowed menu can be shared. services.js then intercepts them
             * and filters in place.
             */ ?>
            <nav class="services-filter" aria-label="Filter by category" data-services-filter>

                <?= $this->Html->link(
                    'All',
                    ['action' => 'index'],
                    [
                        'class' => 'services-filter-link' . ($activeCategory === null ? ' is-active' : ''),
                        'data-filter' => 'all',
                        'aria-current' => $activeCategory === null ? 'true' : false,
                    ]
                ) ?>

                <?php foreach ($categories as $category): ?>
                    <?php $slug = \App\Model\Table\ServicesTable::categorySlug($category); ?>
                    <?= $this->Html->link(
                        $category,
                        ['action' => 'index', '?' => ['category' => $slug]],
                        [
                            'class' => 'services-filter-link'
                                . ($activeCategory === $category ? ' is-active' : ''),
                            'data-filter' => $slug,
                            'aria-current' => $activeCategory === $category ? 'true' : false,
                        ]
                    ) ?>
                <?php endforeach; ?>

            </nav>

            <?php /*
             * Every category is always rendered; the server marks the ones
             * the filter excludes as hidden. The browser honours that with
             * or without scripting, and because the markup is already there
             * the script can switch categories without a round trip.
             */ ?>
            <?php foreach ($menu as $category => $services): ?>

                <?php $slug = \App\Model\Table\ServicesTable::categorySlug($category); ?>

                <section
                    class="services-category"
                    id="cat-<?= h($slug) ?>"
                    data-category="<?= h($slug) ?>"
                    <?= $activeCategory !== null && $activeCategory !== $category ? 'hidden' : '' ?>
                >

                    <h2><?= h($category) ?></h2>

                    <ul class="services-list">

                        <?php foreach ($services as $service): ?>

                            <li class="service-item">

                                <h3><?= h($service->name) ?></h3>

                                <?php if (!empty($service->description)): ?>
                                    <p class="service-description">
                                        <?= nl2br(h($service->description), false) ?>
                                    </p>
                                <?php endif; ?>

                                <?php
                                // Only the parts that are actually set.
                                $meta = array_filter([
                                    $service->price_label,
                                    $service->duration_label,
                                ]);
                                ?>

                                <?php if ($meta !== []): ?>
                                    <p class="service-meta">
                                        <?= h(implode('  •  ', $meta)) ?>
                                    </p>
                                <?php endif; ?>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                </section>

            <?php endforeach; ?>

        <?php endif; ?>

        <div class="services-cta">

            <p>Ready to book? Choose a time on our booking page.</p>

            <a
                class="hero-cta"
                href="<?= h($bookingUrl) ?>"
                target="_blank"
                rel="noopener"
            >
                <?= $this->SiteContent->get('hero.cta') ?>
            </a>

        </div>

    </section>

</main>

<?= $this->element('site_footer') ?>
