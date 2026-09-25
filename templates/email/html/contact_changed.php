<?php
/**
 * Contact change warning, HTML
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
<div class="content">
    <table role="presentation" class="main">
        <tr>
            <td class="wrapper">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <h3>Your contact details were changed</h3>

                            <p>Hi <?= h($first_name) ?>,</p>

                            <p>
                                The <?= h($field) ?> on your
                                <b>Willow and Sage Wellness Studio</b> account was
                                changed from <b><?= h($previous_value) ?></b>
                                to <b><?= h($new_value) ?></b>.
                            </p>

                            <p><b>If you made this change, nothing further is needed.</b></p>

                            <p>
                                If you did not, use the button below. It puts your
                                old <?= h($field) ?> back and sends you a link to
                                set a new password, which locks out whoever made
                                the change.
                            </p>

                            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                <tbody>
                                <tr>
                                    <td align="center" bgcolor="#b3382c" style="border-radius: 6px;">
                                        <a href="<?= $revertUrl ?>" target="_blank"
                                           style="display: inline-block; padding: 14px 32px; font-weight: bold;
                                                  color: #ffffff; text-decoration: none; border-radius: 6px;">
                                            This wasn't me - undo it
                                        </a>
                                    </td>
                                </tr>
                                </tbody>
                            </table>

                            <p>or use the following link:<br>
                                <span style="word-break: break-all;"><?= h($revertUrl) ?></span>
                            </p>

                            <p>This link works for <?= h($days) ?> days.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td class="content-block">
                    Copyright &copy; <?= date('Y') ?> Monash FIT Industry Experience
                </td>
            </tr>
        </table>
    </div>
</div>
