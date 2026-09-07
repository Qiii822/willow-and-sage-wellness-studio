<?php
/**
 * Step 2: choose who does the treatment.
 *
 * "No preference" is offered first and on purpose. It is the honest
 * default for most customers, and it is also the answer that shows the
 * most times at the next step, because Square is then free to offer
 * anyone's diary rather than one person's.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\BookingDraft $draft
 * @var array<int, array{id: string, name: string, description: string|null, image_url: string|null}> $team
 */

$this->assign('title', 'Choose a therapist - ' . $this->SiteContent->raw('site.title'));

$this->Html->css('landing', ['block' => true]);
$this->Html->css('services', ['block' => true]);
$this->Html->css('book', ['block' => true]);

$chosen = (string)$draft->team_member_id;
?>

<?= $this->element('site_header') ?>

<main class="site-main">

    <section class="book-page">

        <?= $this->element('book/steps', ['current' => 2]) ?>

        <header class="book-header">
            <h1>Who would you like?</h1>
            <p>
                Pick someone in particular, or leave it to us and we will show you
                every time the studio has free.
            </p>
        </header>

        <?= $this->element('book/summary', ['draft' => $draft]) ?>

        <?= $this->Form->create(null, ['class' => 'book-form']) ?>

        <ul class="book-people">

            <li class="book-person<?= $chosen === '' ? ' is-chosen' : '' ?>">
                <button type="submit" name="team_member_id" value="" class="book-person-button">
                    <span class="book-person-avatar book-person-any" aria-hidden="true">&#10003;</span>
                    <span class="book-person-body">
                        <span class="book-person-name">No preference</span>
                        <span class="book-person-note">
                            Whoever is available. This shows the most times.
                        </span>
                    </span>
                </button>
            </li>

            <?php foreach ($team as $member): ?>

                <li class="book-person<?= $chosen === $member['id'] ? ' is-chosen' : '' ?>">

                    <button
                        type="submit"
                        name="team_member_id"
                        value="<?= h($member['id']) ?>"
                        class="book-person-button"
                    >

                        <?php if ($member['image_url'] !== null): ?>
                            <img
                                class="book-person-avatar"
                                src="<?= h($member['image_url']) ?>"
                                alt=""
                            >
                        <?php else: ?>
                            <?php /*
                             * Initials rather than a stock silhouette: the
                             * studio has not uploaded a photo, and a generic
                             * one would say less than the person's own name.
                             */ ?>
                            <span class="book-person-avatar book-person-initials" aria-hidden="true">
                                <?= h(mb_strtoupper(mb_substr($member['name'], 0, 1))) ?>
                            </span>
                        <?php endif; ?>

                        <span class="book-person-body">
                            <span class="book-person-name"><?= h($member['name']) ?></span>
                            <?php if ($member['description'] !== null): ?>
                                <span class="book-person-note"><?= h($member['description']) ?></span>
                            <?php endif; ?>
                        </span>

                    </button>

                </li>

            <?php endforeach; ?>

        </ul>

        <?= $this->Form->end() ?>

        <p class="book-back">
            <?= $this->Html->link('Change treatments', ['action' => 'index']) ?>
        </p>

    </section>

</main>
