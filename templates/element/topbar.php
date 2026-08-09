<?php
$today = \Cake\I18n\FrozenDate::today()->format('Y-m-d');
?>

<div class="top-bar">
    <div>
        <h1>Welcome back, Wendy</h1>
        <p class="subtitle">
            Here's what's happening with your business today.
        </p>
    </div>

    <div class="top-actions">
        <button class="notification" type="button">
            <i data-lucide="bell"></i>
        </button>

        <input
            type="date"
            max="<?= h($today) ?>"
            value="<?= h($today) ?>"
        >

        <button class="export-btn" type="button">
            <i data-lucide="download"></i>
            Export
        </button>

        <div class="profile">W</div>
    </div>
</div>
