<?php
/**
 * @var string $customerName
 * @var int $daysSinceLastBooking
 */
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

        <div
            style="
                text-align: center;
                margin-bottom: 30px;
            "
        >
            <h1
                style="
                    margin: 0 0 10px;
                    font-size: 30px;
                    line-height: 1.2;
                    color: #3f3a36;
                "
            >
                We’d love to see you again!
            </h1>

            <p
                style="
                    margin: 0;
                    font-size: 15px;
                    color: #857d77;
                "
            >
                Willow and Sage Wellness Studio
            </p>
        </div>


        <p
            style="
                font-size: 16px;
                line-height: 1.7;
            "
        >
            Hi <?= h($customerName) ?>,
        </p>


        <p
            style="
                font-size: 16px;
                line-height: 1.7;
            "
        >
            It’s been
            <strong>
                <?= h($daysSinceLastBooking) ?> days
            </strong>
            since your last appointment with Willow and Sage.
        </p>


        <p
            style="
                font-size: 16px;
                line-height: 1.7;
            "
        >
            We hope you’ve been well. We’d love to welcome you
            back for another relaxing appointment.
        </p>


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
                style="
                    margin: 0 0 12px;
                    font-size: 20px;
                    color: #3f3a36;
                "
            >
                Ready to book again?
            </h2>

            <p
                style="
                    margin: 0 0 22px;
                    font-size: 15px;
                    line-height: 1.6;
                    color: #69625d;
                "
            >
                Choose a time that works for you using our
                online booking page.
            </p>


            <a
                href="https://willow-and-sage-wellness-studio.square.site/"
                target="_blank"
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
            >
                Book an Appointment
            </a>
        </div>


        <p
            style="
                font-size: 16px;
                line-height: 1.7;
            "
        >
            We look forward to seeing you again soon.
        </p>


        <p
            style="
                margin-top: 28px;
                font-size: 15px;
                line-height: 1.6;
                color: #857d77;
            "
        >
            Warm regards,<br>
            <strong>
                Willow and Sage Wellness Studio
            </strong>
        </p>


        <hr
            style="
                margin: 32px 0 20px;
                border: 0;
                border-top: 1px solid #eee8e3;
            "
        >


        <p
            style="
                margin: 0;
                text-align: center;
                font-size: 12px;
                line-height: 1.6;
                color: #a19892;
            "
        >
            You’re receiving this email because you previously
            booked with Willow and Sage Wellness Studio.
        </p>

    </div>
</div>
