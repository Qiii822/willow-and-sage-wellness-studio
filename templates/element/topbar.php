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

        <form
                method="get"
                action="<?= $this->Url->build([
                    'controller' => 'Dashboard',
                    'action' => 'index'
                ]) ?>"
        >
            <input
                    type="date"
                    name="date"
                    max="<?= h($today) ?>"
                    value="<?= h($selectedDate ?? $today) ?>"
                    onchange="this.form.submit()"
            >
        </form>

        <button class="export-btn" type="button">
            <i data-lucide="download"></i>
            Export
        </button>

        <div class="profile">W</div>
    </div>
</div>
