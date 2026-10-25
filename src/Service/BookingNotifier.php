<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\AustralianMobile;
use App\Model\Table\BookingAccessCodesTable;
use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * Gets a sign-in code to the customer, by text, and says whether the one
 * they typed is right.
 *
 * Two ways, chosen by the environment:
 *
 * - With TWILIO_VERIFY_SERVICE_SID set, Twilio Verify makes the code,
 *   texts it and checks it. The code this site issued is not used.
 * - Otherwise the site's own code goes out through SmsSender, and so is
 *   really texted or only logged as SMS_TRANSPORT says. While it is only
 *   logged, and only with debug on, the code is also handed back for the
 *   page to show, so the flow can be walked without a phone.
 */
class BookingNotifier
{
    protected SmsSender $sms;

    protected TwilioVerify $verify;

    public function __construct(?SmsSender $sms = null, ?TwilioVerify $verify = null)
    {
        $this->sms = $sms ?? new SmsSender();
        $this->verify = $verify ?? new TwilioVerify();
    }

    /**
     * @param string $phone Where it should go, as the customer typed it.
     * @param string $code The six digits this site issued.
     * @return array{sent: bool, too_many: bool, shown: string|null} Whether
     *  the text went; whether Twilio refused it for having sent this number
     *  too many already; and the code, when it is safe to display it.
     */
    public function sendAccessCode(string $phone, string $code): array
    {
        // Not the code: a log line is a poor place for a working key, and
        // this one names who it was for.
        Log::info(sprintf(
            'Booking access code issued for a number ending %s',
            substr(preg_replace('/\D/', '', $phone) ?? '', -3),
        ));

        $to = AustralianMobile::normalise($phone) ?? $phone;

        if ($this->verify->isEnabled()) {
            $result = $this->verify->send($to);

            return [
                'sent' => $result === TwilioVerify::SENT,
                'too_many' => $result === TwilioVerify::TOO_MANY,
                'shown' => null,
            ];
        }

        $sent = $this->sms->send(
            $to,
            sprintf(
                'Your Willow and Sage code to see your bookings is %s. It expires in %d minutes.',
                $code,
                BookingAccessCodesTable::LIFETIME_MINUTES,
            ),
        );

        return [
            'sent' => $sent,
            'too_many' => false,
            'shown' => $this->sms->isSimulated() && Configure::read('debug') ? $code : null,
        ];
    }

    /**
     * Whether Twilio Verify is checking codes rather than this site.
     *
     * @return bool
     */
    public function checksCodes(): bool
    {
        return $this->verify->isEnabled();
    }

    /**
     * Asks Twilio Verify whether this is the code it sent.
     *
     * Only meaningful when checksCodes() is true; otherwise the code is the
     * site's own and BookingAccessCodesTable::verify() checks it.
     *
     * @param string $phone As the customer typed it.
     * @param string $code
     * @return string One of TwilioVerify::APPROVED, WRONG or SPENT.
     */
    public function checkAccessCode(string $phone, string $code): string
    {
        return $this->verify->check(AustralianMobile::normalise($phone) ?? $phone, $code);
    }
}
