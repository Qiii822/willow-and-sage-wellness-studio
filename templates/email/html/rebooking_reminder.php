<?php
/**
 * @var string $customerName
 * @var int $daysSinceLastBooking
 */
?>

<div style="
    max-width: 600px;
    margin: 0 auto;
    padding: 40px;
    font-family: Arial, sans-serif;
    color: #37322e;
">

    <h1 style="
        margin-bottom: 20px;
        font-size: 28px;
    ">
        We'd love to see you again!
    </h1>

    <p>
        Hi <?= h($customerName) ?>,
    </p>

    <p>
        It's been <?= h($daysSinceLastBooking) ?> days
        since your last appointment with Willow and Sage.
    </p>

    <p>
        We hope you've been well. We'd love to welcome
        you back for another appointment.
    </p>

    <div style="
        margin: 30px 0;
        padding: 20px;
        background: #f4efe9;
        border-radius: 10px;
    ">

        <strong>
            Ready to book again?
        </strong>

        <p style="margin-bottom: 0;">
            Contact Willow and Sage to arrange your
            next appointment.
        </p>

    </div>

    <p>
        We look forward to seeing you again soon!
    </p>

    <p>
        Willow and Sage
    </p>

</div>
