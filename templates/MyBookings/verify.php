<?php
/**
 * Type the code that was sent.
 *
 * @var \App\View\AppView $this
 * @var string $phone
 * @var string|null $shownCode Development only; null in production.
 */

$this->assign('title', 'Enter your code - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page book-page-narrow">

        <header class="book-header">
            <h1>Enter your code</h1>
            <p>
                We have sent six digits to <strong><?= h($phone) ?></strong>.
                It is good for ten minutes.
            </p>
        </header>

        <?php if ($shownCode !== null): ?>
            <?php /*
             * Development only. The notifier hands the code back just here,
             * so the flow can be walked without an SMS service existing yet.
             */ ?>
            <p class="book-empty">
                No SMS service is connected yet, so here is the code:
                <strong><?= h($shownCode) ?></strong>
            </p>
        <?php endif; ?>

        <?= $this->Form->create(null, ['class' => 'book-form book-details']) ?>

        <div class="book-field">
            <label for="code">Code</label>
            <?= $this->Form->text('code', [
                'id' => 'code',
                'class' => 'book-input book-code',
                'required' => true,
                'maxlength' => 6,
                'inputmode' => 'numeric',
                'pattern' => '[0-9]*',
                'autocomplete' => 'one-time-code',
                'autofocus' => true,
            ]) ?>
        </div>

        <div class="book-actions">
            <?= $this->Form->button('Show my bookings', ['class' => 'hero-cta']) ?>
            <?= $this->Form->button('Send another code', [
                'class' => 'book-secondary',
                'name' => 'resend',
                'value' => '1',
                'formnovalidate' => true,
            ]) ?>
        </div>

        <?= $this->Form->end() ?>

        <p class="book-back">
            <?= $this->Html->link('Use a different number', ['action' => 'signOut']) ?>
        </p>

    </section>

</main>
