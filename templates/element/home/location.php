<?php
/**
 * Home page block: where the studio is and when it is open.
 *
 * @var \App\View\AppView $this
 * @var string $variant Arrangement chosen in the editor.
 */

$this->Html->css('location', ['block' => true]);

$address = trim($this->SiteContent->raw('location.address'));
$phone = trim($this->SiteContent->raw('footer.phone'));
$email = trim($this->SiteContent->raw('footer.email'));

/*
 * Hours are typed a line per day as "Monday: 9:00 am - 7:00 pm", and split
 * on the first colon so the day and the time can sit in their own columns.
 * A line without a colon is still shown, rather than dropped, so a typo
 * loses its alignment instead of losing the day entirely.
 */
$hours = [];

foreach (preg_split('/\R/', trim($this->SiteContent->raw('location.hours'))) ?: [] as $line) {
    $line = trim($line);

    if ($line === '') {
        continue;
    }

    $parts = explode(':', $line, 2);

    $hours[] = count($parts) === 2
        ? ['day' => trim($parts[0]), 'time' => trim($parts[1])]
        : ['day' => $line, 'time' => ''];
}

/*
 * Sent to Google Maps rather than embedded. An embedded map needs an API
 * key the studio does not have, and would load third-party tracking into
 * every visit; a link opens the map the visitor already has set up, with
 * directions from wherever they actually are.
 */
$directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination='
    . rawurlencode(preg_replace('/\s+/', ' ', $address));
?>

<section class="feature-strip" id="location">

    <div class="feature-strip-inner">

        <div class="section-intro location-heading">
            <h2><?= $this->SiteContent->get('location.heading') ?></h2>
        </div>

        <div class="location-grid location-grid-<?= h($variant) ?>">

            <div class="location-details">

                <?php if ($address !== ''): ?>
                    <address class="location-address">
                        <?= $this->SiteContent->lines('location.address') ?>
                    </address>
                <?php endif; ?>

                <ul class="location-contact">
                    <?php if ($phone !== ''): ?>
                        <li>
                            <?php // tel: needs the number without spaces to dial. ?>
                            <a href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone)) ?>">
                                <?= h($phone) ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($email !== ''): ?>
                        <li><a href="mailto:<?= h($email) ?>"><?= h($email) ?></a></li>
                    <?php endif; ?>
                </ul>

                <?php if ($address !== ''): ?>
                    <a
                        class="location-directions"
                        href="<?= h($directionsUrl) ?>"
                        target="_blank"
                        rel="noopener"
                    >
                        Get directions
                    </a>
                <?php endif; ?>

            </div>

            <?php if ($hours !== []): ?>
                <div class="location-hours">
                    <h3>Opening hours</h3>

                    <dl>
                        <?php foreach ($hours as $row): ?>
                            <div class="location-hours-row">
                                <dt><?= h($row['day']) ?></dt>
                                <dd><?= h($row['time']) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            <?php endif; ?>

        </div>

    </div>

</section>
