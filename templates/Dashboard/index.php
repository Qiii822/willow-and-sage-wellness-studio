<?php
/** @var \App\View\AppView $this */
/** @var string $accessToken */
/** @var array $tokenPayload */
?>

<div style="max-width: 800px; margin: 3rem auto; font-family: Arial, sans-serif;">
    <h1>Welcome to Dashboard</h1>
    
    <?php if (!empty($tokenPayload['merchant_id'])): ?>
        <p>Logged in as: <strong><?= h($tokenPayload['merchant_id']) ?></strong></p>
    <?php endif; ?>
    
    <p>You are successfully authenticated with Square!</p>
    
    <nav>
        <ul>
            <li><a href="/">Home</a></li>
            <li><a href="/auth/logout">Logout</a></li>
        </ul>
    </nav>
</div>