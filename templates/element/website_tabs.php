<?php
/**
 * The three screens that change what the public site says.
 *
 * They used to sit as three separate entries in the sidebar, which read as
 * three unrelated places when they are really one job - editing the
 * website - split by what is being edited. One sidebar entry now leads
 * here, and this is where the split happens.
 *
 * @var \App\View\AppView $this
 */

$controller = $this->request->getParam('controller');

$tabs = [
    [
        'label' => 'Page content',
        'hint' => 'Words and photos on the home page',
        'url' => ['controller' => 'SiteContents', 'action' => 'index'],
        'active' => $controller === 'SiteContents',
    ],
    [
        'label' => 'Services',
        'hint' => 'The treatment menu',
        'url' => ['controller' => 'Services', 'action' => 'manage'],
        'active' => $controller === 'Services',
    ],
    [
        'label' => 'Offers',
        'hint' => 'Discounts shown on the home page',
        'url' => ['controller' => 'Offers', 'action' => 'manage'],
        'active' => $controller === 'Offers',
    ],
];
?>

<nav class="website-tabs" aria-label="Website sections">

    <?php foreach ($tabs as $tab): ?>

        <?= $this->Html->link(
            '<span class="website-tab-label">' . h($tab['label']) . '</span>'
            . '<span class="website-tab-hint">' . h($tab['hint']) . '</span>',
            $tab['url'],
            [
                'escape' => false,
                'class' => 'website-tab' . ($tab['active'] ? ' is-current' : ''),
                // Tells a screen reader which one it is already on, rather
                // than leaving the styling to say it alone.
                'aria-current' => $tab['active'] ? 'page' : false,
            ]
        ) ?>

    <?php endforeach; ?>

</nav>
