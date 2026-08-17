<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */

$cakeDescription = 'CakePHP: the rapid development php framework';
?>
<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= $cakeDescription ?>:
        <?= $this->fetch('title') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake', 'variables']) ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>

    <script src="https://unpkg.com/lucide@latest"></script>

</head>
<body>
<?php if (
    !(
        $this->request->getParam('controller') === 'Users' &&
        $this->request->getParam('action') === 'login'
    )
): ?>

    <nav class="top-nav">
        <div class="top-nav-title">
            <a href="<?= $this->Url->build('/') ?>">
                <span>Cake</span>PHP
            </a>
        </div>

        <div class="top-nav-links">
            <?php $identity = $this->request->getAttribute('identity'); ?>

            <?php if ($identity): ?>
                <!-- keep your existing logged-in links here -->
            <?php else: ?>
                <?= $this->Html->link(
                    'Login',
                    ['controller' => 'Users', 'action' => 'login']
                ) ?>
            <?php endif; ?>
        </div>
    </nav>

<?php endif; ?>
    <main class="main">
        <div class="container">
            <?= $this->Flash->render() ?>
            <?= $this->fetch('content') ?>
        </div>
    </main>
    <footer>
    </footer>

    <script>
        if (window.lucide) {
            lucide.createIcons();
        }
    </script>

</body>
</html>
