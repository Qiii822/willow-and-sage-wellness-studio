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
</div>