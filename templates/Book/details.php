<?php
/**
 * Step 3: contact details and a discount code.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\BookingDraft $draft
 * @var string $timezone
 */

$this->assign('title', 'Your details - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page">

        <?= $this->element('book/steps', ['current' => 4]) ?>

        <header class="book-header">
            <h1>Your details</h1>
            <p>Almost done. We will send your confirmation to the email you give us.</p>
        </header>

        <?= $this->element('book/summary', ['draft' => $draft, 'timezone' => $timezone]) ?>

        <?= $this->Form->create($draft, ['class' => 'book-form book-details']) ?>

        <div class="book-field">
            <label for="customer-name">Name</label>
            <?= $this->Form->text('customer_name', [
                'id' => 'customer-name',
                'class' => 'book-input',
                'required' => true,
                'maxlength' => 150,
                'autocomplete' => 'name',
            ]) ?>
            <?= $this->Form->error('customer_name') ?>
        </div>

        <div class="book-field">
            <label for="customer-email">Email</label>
            <?= $this->Form->email('customer_email', [
                'id' => 'customer-email',
                'class' => 'book-input',
                'required' => true,
                'maxlength' => 190,
                'autocomplete' => 'email',
            ]) ?>
            <p class="book-help">Your confirmation and any changes go here.</p>
            <?= $this->Form->error('customer_email') ?>
        </div>

        <div class="book-field">
            <label for="customer-phone">Mobile</label>
            <?= $this->Form->tel('customer_phone', [
                'id' => 'customer-phone',
                'class' => 'book-input',
                'required' => true,
                'maxlength' => 30,
                'autocomplete' => 'tel',
                'placeholder' => '0412 345 678',
            ]) ?>
            <p class="book-help">
                An Australian mobile - 0412 345 678 or +61 412 345 678. We use it
                to reach you about this appointment, and it is how you look the
                booking up later.
            </p>
            <?= $this->Form->error('customer_phone') ?>
        </div>

        <div class="book-field">
            <label for="customer-note">Anything we should know</label>
            <?= $this->Form->textarea('customer_note', [
                'id' => 'customer-note',
                'class' => 'book-input',
                'rows' => 3,
                'maxlength' => 500,
            ]) ?>
            <p class="book-help">
                Optional. Injuries, allergies, pregnancy, or anything else that helps
                us look after you.
            </p>
            <?= $this->Form->error('customer_note') ?>
        </div>

        <?php /*
         * Present but not yet wired to anything. Left visible so the layout
         * and the flow can be judged whole; it does nothing until the code
         * scheme exists.
         */ ?>
        <div class="book-field">
            <label for="discount-code">Discount code</label>
            <?= $this->Form->text('discount_code_input', [
                'id' => 'discount-code',
                'class' => 'book-input',
                'maxlength' => 40,
                'placeholder' => 'If you have one',
                'disabled' => true,
            ]) ?>
            <p class="book-help">Not accepting codes yet.</p>
        </div>

        <div class="cf-turnstile"
             data-sitekey="<?= h(env('TURNSTILE_SITE_KEY')) ?>">
        </div>

        <div class="book-actions">
            <?= $this->Form->button('Confirm booking', ['class' => 'hero-cta']) ?>
        </div>

        <?= $this->Form->end() ?>

        <p class="book-back">
            <?= $this->Html->link('Choose a different time', ['action' => 'time', $draft->token]) ?>
        </p>

    </section>

</main>
