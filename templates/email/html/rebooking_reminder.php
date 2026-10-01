<?php
/**
 * Rebooking reminder, HTML.
 *
 * The wording is edited on the settings page; the body lives in an element
 * so the preview there renders exactly this.
 *
 * @var \App\View\AppView $this
 * @var array<string, string> $content Wording with {name} and {days} filled in.
 */
?>
<?= $this->element('email/rebooking_reminder', ['content' => $content]) ?>
