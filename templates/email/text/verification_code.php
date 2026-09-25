<?php
/**
 * Contact verification code, plain text
 *
 * @var \App\View\AppView $this
 * @var string $first_name
 * @var string $code
 * @var int $minutes
 */
?>
Confirm your email address

Hi <?= h($first_name) ?>,

Use this code to confirm this address on your Willow and Sage Wellness Studio account:

<?= h($code) ?>


The code expires in <?= h($minutes) ?> minutes.

If you did not ask to change your contact details, you can ignore this email -
nothing has changed on your account.

Copyright (c) <?= date('Y') ?> Monash FIT Industry Experience
