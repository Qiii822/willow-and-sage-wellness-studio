<?php
/**
 * Ask for the phone number the booking was made with.
 *
 * @var \App\View\AppView $this
 */

$this->assign('title', 'My bookings - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page book-page-narrow">

        <header class="book-header">
            <h1>My bookings</h1>
            <p>
                There is no account to remember. Enter the mobile number you booked
                with and we will text you a code.
            </p>
        </header>

        <?= $this->Form->create(null, ['class' => 'book-form book-details']) ?>

        <div class="book-field">
            <label for="phone">Mobile number</label>
            <?= $this->Form->tel('phone', [
                'id' => 'phone',
                'class' => 'book-input',
                'required' => true,
                'maxlength' => 30,
                'autocomplete' => 'tel',
                'autofocus' => true,
                'placeholder' => '0412 345 678',
            ]) ?>
            <p class="book-help">
                The same number, however you write it - with spaces, with +61, or
                with a leading zero.
            </p>
        </div>

        <div class="book-actions">
            <?= $this->Form->button('Send me a code', ['class' => 'hero-cta']) ?>
        </div>

        <?= $this->Form->end() ?>

        <?php $phone = trim($this->SiteContent->raw('footer.phone')); ?>

        <?php if ($phone !== ''): ?>
            <p class="book-back">
                Booked over the phone, or changed your number? Call us on
                <a href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= h($phone) ?></a>.
            </p>
        <?php endif; ?>

    </section>

</main>
