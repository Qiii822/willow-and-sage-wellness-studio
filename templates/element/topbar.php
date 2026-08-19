<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User|null $identity
 * @var string $selectedDate
 */

$today = \Cake\I18n\FrozenDate::today()
    ->format('Y-m-d');
?>

<div class="top-bar">
    <div>
        <h1>Welcome back, <?= h($identity->first_name ?? '') ?></h1>
        <p class="subtitle">
            Here's what's happening with the business today.
        </p>
    </div>

    <div class="top-actions">

        <form
                class="date-filter-form"
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

        <button
            class="export-btn"
            type="button"
            id="export-customers"
            data-date="<?= h($selectedDate ?? $today) ?>"
        >
            <i data-lucide="download"></i>
            Export customers
        </button>

    </div>
</div>


