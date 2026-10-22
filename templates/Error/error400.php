<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 * @var string $url
 */

use Cake\Core\Configure;

$this->setLayout('error');

$this->assign('title', 'Page Not Found');

$this->Html->css(
    'error',
    ['block' => true]
);

if (Configure::read('debug')) :
    $this->setLayout('dev_error');

    $this->assign('title', $message);
    $this->assign('templateName', 'error400.php');

    $this->start('file');
    echo $this->element('auto_table_warning');
    $this->end();

else :
    ?>

    <div class="error-page">

        <div class="error-card">

            <div class="error-code">
                404
            </div>

            <h1>Page not found</h1>

            <p>
                The page you're looking for doesn't exist or may have been moved.
            </p>

            <div class="error-actions">

                <?= $this->Html->link(
                    'Home',
                    [
                        'controller' => 'Home',
                        'action' => 'index'
                    ],
                    [
                        'class' => 'error-button'
                    ]
                ) ?>

                <button
                    type="button"
                    class="error-button secondary"
                    onclick="history.back()"
                >
                    Go Back
                </button>

            </div>

        </div>

    </div>

<?php endif; ?>
