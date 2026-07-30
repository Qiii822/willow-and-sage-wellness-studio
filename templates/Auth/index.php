<?php
/** @var \App\View\AppView $this */
?>
<div style="max-width: 600px; margin: 3rem auto; font-family: Arial, sans-serif;">
    <h1>Square OAuth</h1>
    <p>This page starts the Square authorization flow.</p>
    <p>
        <a href="<?= $this->Url->build(['controller' => 'Auth', 'action' => 'login']) ?>" style="display: inline-block; padding: 0.75rem 1rem; background: #006aff; color: white; text-decoration: none; border-radius: 4px;">
            Connect Square
        </a>
    </p>
</div>
