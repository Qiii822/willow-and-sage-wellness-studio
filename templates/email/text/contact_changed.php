<?php
/**
 * Contact change warning, plain text
 *
 * @var \App\View\AppView $this
 * @var string $first_name
 * @var string $field
 * @var string $previous_value
 * @var string $new_value
 * @var string $token
 * @var int $days
 */

$revertUrl = $this->Url->build(
    ['controller' => 'Users', 'action' => 'revertContactChange', $token],
    ['fullBase' => true]
);
?>
Your contact details were changed

Hi <?= h($first_name) ?>,

The <?= h($field) ?> on your Willow and Sage Wellness Studio account was changed
from <?= h($previous_value) ?> to <?= h($new_value) ?>.

If you made this change, nothing further is needed.

If you did not, open the link below. It puts your old <?= h($field) ?> back and
sends you a link to set a new password, which locks out whoever made the change.

<?= $revertUrl ?>


This link works for <?= h($days) ?> days.

Copyright (c) <?= date('Y') ?> Monash FIT Industry Experience
