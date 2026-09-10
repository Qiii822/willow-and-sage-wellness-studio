<?php
/**
 * Step 1: choose the treatments.
 *
 * Built as a cart rather than a plain list, the way the studio's Square
 * site works: treatments are added one at a time and what has been chosen
 * stays visible, with a running total, while the menu is still being read.
 *
 * The mechanism underneath is still a checkbox per treatment inside one
 * form, so the page works with scripting off - the Add buttons are labels,
 * and the cart is a summary of what is ticked. book_cart.js only keeps that
 * summary up to date without a round trip.
 *
 * @var \App\View\AppView $this
 * @var array<string, array<int, \App\Model\Entity\Service>> $menu
 */

$this->assign('title', 'Book - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);
$this->Html->script('book_cart', ['block' => true]);
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page book-page-wide">

        <?= $this->element('book/steps', ['current' => 1]) ?>

        <header class="book-header">
            <h1>Choose your treatments</h1>
            <p>Add as many as you like. We will find times that fit them all together.</p>
        </header>

        <?php if ($menu === []): ?>

            <p class="services-empty">
                The treatment list is being updated. Please call the studio to book.
            </p>

        <?php else: ?>

            <?= $this->Form->create(null, ['class' => 'book-form book-layout']) ?>

            <div class="book-menu">

                <?php foreach ($menu as $category => $services): ?>

                    <section class="book-category">

                        <h2><?= h($category) ?></h2>

                        <ul class="book-choices">

                            <?php foreach ($services as $service): ?>

                                <?php
                                $bookable = !empty($service->square_variation_id);
                                $fieldId = 'treatment-' . h($service->id);
                                ?>

                                <li
                                    class="book-choice<?= $bookable ? '' : ' is-unavailable' ?>"
                                    data-treatment
                                    data-name="<?= h($service->name) ?>"
                                    data-price="<?= (int)$service->price_cents ?>"
                                    data-duration="<?= (int)$service->duration_minutes ?>"
                                >

                                    <?php /*
                                     * The checkbox is the real control and is
                                     * still reachable by keyboard; the Add
                                     * button is a label for it, so clicking
                                     * either one does the same thing with or
                                     * without scripting.
                                     */ ?>
                                    <?= $this->Form->checkbox('service_ids[]', [
                                        'value' => $service->id,
                                        'id' => $fieldId,
                                        'hiddenField' => false,
                                        'disabled' => !$bookable,
                                        'class' => 'book-choice-input',
                                    ]) ?>

                                    <div class="book-choice-body">

                                        <p class="book-choice-name"><?= h($service->name) ?></p>

                                        <?php if (!empty($service->description)): ?>
                                            <p class="book-choice-description">
                                                <?= h($service->description) ?>
                                            </p>
                                        <?php endif; ?>

                                        <p class="book-choice-meta">
                                            <?= h($service->price_label) ?>
                                            <?php if ($service->duration_label !== null): ?>
                                                &middot; <?= h($service->duration_label) ?>
                                            <?php endif; ?>
                                        </p>

                                        <?php if (!$bookable): ?>
                                            <p class="book-choice-note">
                                                Not available to book online - please call
                                            </p>
                                        <?php endif; ?>

                                    </div>

                                    <?php if ($bookable): ?>
                                        <label class="book-add" for="<?= $fieldId ?>">
                                            <span class="book-add-idle">Add</span>
                                            <span class="book-add-done">Added</span>
                                        </label>
                                    <?php endif; ?>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </section>

                <?php endforeach; ?>

            </div>

            <?php /*
             * Sticky, so the running total stays in view while the menu is
             * scrolled - the whole point of a cart is knowing what is in it
             * without going back to look.
             */ ?>
            <?php /*
             * The studio's longest opening, so the script can say the cart
             * has outgrown a single day while it is still being filled -
             * rather than letting the customer find out two steps later on
             * a page with no times on it. Absent when Square could not be
             * reached, and then the warning simply does not appear.
             */ ?>
            <aside
                class="book-cart"
                data-cart
                <?= $longestDayMinutes !== null
                    ? 'data-longest-day="' . (int)$longestDayMinutes . '"'
                    : '' ?>
                data-longest-day-label="<?= h($this->Book->duration($longestDayMinutes)) ?>"
            >

                <h2>Your treatments</h2>

                <p class="book-cart-empty" data-cart-empty>
                    Nothing added yet. Pick a treatment from the menu.
                </p>

                <ul class="book-cart-items" data-cart-items hidden></ul>

                <dl class="book-cart-totals" data-cart-totals hidden>
                    <div>
                        <dt>Time</dt>
                        <dd data-cart-duration></dd>
                    </div>
                    <div class="book-cart-total">
                        <dt>Total</dt>
                        <dd data-cart-total></dd>
                    </div>
                </dl>

                <?php /*
                 * Filled in and shown by the script as treatments are
                 * added. Empty and hidden to begin with, so a page without
                 * scripting shows nothing here and relies on the check the
                 * server does when the form is submitted.
                 */ ?>
                <p class="book-cart-warning" data-cart-too-long hidden></p>

                <?php if ($cartError !== null): ?>
                    <?php /*
                     * Above the button rather than below it: it explains why
                     * pressing the button did nothing, and it is read on the
                     * way to pressing it again.
                     *
                     * role="alert" so it is announced when the page comes
                     * back, which is the only thing that visibly changed.
                     */ ?>
                    <p class="book-cart-error" role="alert" data-cart-error>
                        <?= h($cartError) ?>
                    </p>
                <?php endif; ?>

                <div class="book-actions">
                    <?= $this->Form->button('Find a time', ['class' => 'hero-cta']) ?>
                </div>

                <p class="book-cart-note">
                    Nothing to pay now - you settle up at the studio after your treatment.
                </p>

            </aside>

            <?= $this->Form->end() ?>

        <?php endif; ?>

    </section>

</main>
