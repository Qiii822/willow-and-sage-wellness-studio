<?php
/** @var \App\View\AppView $this */
/** @var string $merchantId */
?>

<div style="max-width: 800px; margin: 3rem auto; font-family: Arial, sans-serif;">
    <h1>Welcome to Dashboard</h1>
    <?php if (!empty($merchantId)): ?>
        <p>Logged in as: <strong><?= h($merchantId) ?></strong></p>
    <?php endif; ?>
    <p>You are successfully authenticated with Square!</p>

    <nav>
        <ul>
            <li><a href="/">Home</a></li>
        </ul>
    </nav>

    <hr>

    <?= $this->Form->create(null, ['url' => ['controller' => 'Auth', 'action' => 'logout']]) ?>
        <p>
            <label>
                <?= $this->Form->checkbox('revoke_square', ['hiddenField' => false]) ?>
                Also disconnect this Square account entirely (you'll need to reauthorize to use it again)
            </label>
        </p>
        <?= $this->Form->button('Logout', ['type' => 'submit']) ?>
    <?= $this->Form->end() ?>
</div>