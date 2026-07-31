<?php
/** @var \App\View\AppView $this */
/** @var string|null $accessToken */
/** @var array|null $tokenPayload */
?>
<div style="max-width: 800px; margin: 3rem auto; font-family: Arial, sans-serif;">
    <h1>Square OAuth success</h1>
    <p>The token exchange completed.</p>

    <?php if ($accessToken) : ?>
        <p><strong>Access token:</strong><br><?= h($accessToken) ?></p>
    <?php else : ?>
        <p>No access token was stored.</p>
    <?php endif; ?>

    <?php if (!empty($tokenPayload)) : ?>
        <pre><?= h(print_r($tokenPayload, true)) ?></pre>
    <?php endif; ?>
</div>
