<?php
/**
 * Contact verification code, HTML
 *
 * @var \App\View\AppView $this
 * @var string $first_name
 * @var string $code
 * @var int $minutes
 */
?>
<div class="content">
    <table role="presentation" class="main">
        <tr>
            <td class="wrapper">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <h3>Confirm your email address</h3>

                            <p>Hi <?= h($first_name) ?>,</p>

                            <p>
                                Use this code to confirm this address on your
                                <b>Willow and Sage Wellness Studio</b> account:
                            </p>

                            <p style="margin: 24px 0; font-size: 32px; font-weight: bold;
                                      letter-spacing: 8px; color: #8b7e74;">
                                <?= h($code) ?>
                            </p>

                            <p>
                                The code expires in <?= h($minutes) ?> minutes.
                            </p>

                            <p>
                                If you did not ask to change your contact details,
                                you can ignore this email - nothing has changed on
                                your account.
                            </p>
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
