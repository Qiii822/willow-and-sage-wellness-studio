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
 * One place drives both the map and the directions link. The override
 * exists because a geocoder can miss on a business name where it would
 * find the plain street address.
 */
$mapQuery = trim($this->SiteContent->raw('location.map_query'));

if ($mapQuery === '') {
    $mapQuery = (string)preg_replace('/\s+/', ' ', $address);
}

$showMap = $variant === 'map' && $mapQuery !== '';

/*
 * Directions open in Google Maps rather than being handled here, so the
 * route starts from wherever the visitor actually is.
 */
$directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination='
    . rawurlencode($mapQuery);

$mapUrl = 'https://maps.google.com/maps?q=' . rawurlencode($mapQuery)
    . '&hl=en&z=16&output=embed';
?>

<section class="feature-strip" id="location">

    <div class="feature-strip-inner">

        <div class="section-intro location-heading">
            <h2><?= $this->SiteContent->get('location.heading') ?></h2>
        </div>

        <div class="location-layout location-layout-<?= h($variant) ?>">

            <?php if ($showMap): ?>
                <div class="location-map">
                    <?php /*
                     * Google's keyless embed. The Maps Embed API proper wants
                     * an API key and a billing account, which is a lot of
                     * setup for a pin on one address.
                     *
                     * Loaded lazily so the third-party frame is only fetched
                     * if the visitor scrolls this far, and the referrer is
                     * trimmed rather than handing over the full URL.
                     */ ?>
                    <iframe
                        src="<?= h($mapUrl) ?>"
                        title="Map showing where the studio is"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                    ></iframe>
                </div>
            <?php endif; ?>

            <div class="location-info">

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
                                    <i data-lucide="phone" aria-hidden="true"></i>
                                    <span><?= h($phone) ?></span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ($email !== ''): ?>
                            <li>
                                <a href="mailto:<?= h($email) ?>">
                                    <i data-lucide="mail" aria-hidden="true"></i>
                                    <span><?= h($email) ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <?php if ($mapQuery !== ''): ?>
                        <a
                            class="location-directions"
                            href="<?= h($directionsUrl) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            <i data-lucide="navigation" aria-hidden="true"></i>
                            <span>Get directions</span>
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

    </div>

</section>
