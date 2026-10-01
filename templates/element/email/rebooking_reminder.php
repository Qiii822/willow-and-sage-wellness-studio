<?php
/**
 * The rebooking reminder's body.
 *
 * Shared by the email itself and the preview on the settings page, so what
 * staff see while editing is exactly what customers receive. Each piece of
 * wording carries data-email-field so the preview can update it as they
 * type; email clients ignore the attribute.
 *
 * Only the words come from $content - the layout, colours and booking link
 * stay here, so nothing typed on the settings page can break the email.
 *
 * @var \App\View\AppView $this
 * @var array<string, string> $content Wording keyed without the "rebooking." prefix.
 */

$text = fn (string $key): string => nl2br(h($content[$key] ?? ''));
?>
<div
    style="
        margin: 0;
        padding: 40px 20px;
        background: #f7f4f1;
        font-family: Arial, Helvetica, sans-serif;
        color: #3f3a36;
    "
>
    <div
        style="
            max-width: 620px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 18px;
            padding: 42px 38px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        "
    >

        <div style="text-align: center; margin-bottom: 30px;">
            <h1
                data-email-field="heading"
                style="margin: 0 0 10px; font-size: 30px; line-height: 1.2; color: #3f3a36;"
            ><?= $text('heading') ?></h1>

            <p style="margin: 0; font-size: 15px; color: #857d77;">
                Willow and Sage Wellness Studio
            </p>
        </div>

        <p data-email-field="greeting" style="font-size: 16px; line-height: 1.7;"><?= $text('greeting') ?></p>

        <p data-email-field="intro" style="font-size: 16px; line-height: 1.7;"><?= $text('intro') ?></p>

        <p data-email-field="body" style="font-size: 16px; line-height: 1.7;"><?= $text('body') ?></p>

        <div
            style="
                margin: 32px 0;
                padding: 28px;
                background: #f4efe9;
                border-radius: 14px;
                text-align: center;
            "
        >
            <h2
                data-email-field="cta_heading"
                style="margin: 0 0 12px; font-size: 20px; color: #3f3a36;"
            ><?= $text('cta_heading') ?></h2>

            <p
                data-email-field="cta_text"
                style="margin: 0 0 22px; font-size: 15px; line-height: 1.6; color: #69625d;"
            ><?= $text('cta_text') ?></p>

            <a
                href="https://willow-and-sage-wellness-studio.square.site/"
                target="_blank"
                data-email-field="button"
                style="
                    display: inline-block;
                    padding: 14px 28px;
                    background: #675b52;
                    color: #ffffff;
                    text-decoration: none;
                    border-radius: 10px;
                    font-size: 16px;
                    font-weight: 600;
                "
            ><?= $text('button') ?></a>
        </div>

        <p data-email-field="closing" style="font-size: 16px; line-height: 1.7;"><?= $text('closing') ?></p>

        <p
            data-email-field="signoff"
            style="margin-top: 28px; font-size: 15px; line-height: 1.6; color: #857d77;"
        ><?= $text('signoff') ?></p>

        <hr style="margin: 32px 0 20px; border: 0; border-top: 1px solid #eee8e3;">

        <p
            data-email-field="footer"
            style="margin: 0; text-align: center; font-size: 12px; line-height: 1.6; color: #a19892;"
        ><?= $text('footer') ?></p>

    </div>
</div>
