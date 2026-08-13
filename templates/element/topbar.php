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


        <input
            type="date"
            max="<?= h($today) ?>"
            value="<?= h($today) ?>"
        >



        <div class="profile">W</div>
    </div>
</div>
