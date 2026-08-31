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

$bookingUrl = $this->SiteContent->raw('booking.url');
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="services-page">

        <header class="services-header">
            <p class="services-eyebrow"><?= $this->SiteContent->get('services.heading') ?></p>
            <h1><?= $this->SiteContent->get('services.menu_heading') ?></h1>
            <p class="services-lead"><?= $this->SiteContent->get('services.intro') ?></p>
        </header>

        <?php if ($menu === []): ?>

            <p class="services-empty">
                The treatment list is being updated. Please use the button
                below to see everything available and book.
            </p>

        <?php else: ?>

            <?php /*
             * Jump links, so a long menu can be skimmed. Anchors are built
             * from the category name rather than an id, since the category
             * is what the reader sees.
             */ ?>
            <nav class="services-jump" aria-label="Treatment categories">
                <?php foreach (array_keys($menu) as $category): ?>
                    <a href="#cat-<?= h(\Cake\Utility\Text::slug(strtolower($category))) ?>">
                        <?= h($category) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php foreach ($menu as $category => $services): ?>

                <section
                    class="services-category"
                    id="cat-<?= h(\Cake\Utility\Text::slug(strtolower($category))) ?>"
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
