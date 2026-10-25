<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;
use Cake\Log\Log;
use Throwable;

/**
 * Sends SMS, or pretends to.
 *
 * Delivery goes through here whichever way it happens: the `log` driver
 * writes the message to the application log so the whole journey can be
 * built and tested without a phone, and `twilio` really sends it.
 *
 * Pick the driver with SMS_TRANSPORT in config/.env:
 *   log     - default; writes to logs/debug.log and never leaves the machine
 *   none    - reports failure, to exercise the "could not send" path
 *   twilio  - sends through Twilio; needs TWILIO_ACCOUNT_SID,
 *             TWILIO_AUTH_TOKEN and TWILIO_FROM as well
 *
 * Adding a provider means one more branch in send() plus its credentials in
 * the environment; nothing that calls this class has to change.
 */
class SmsSender
{
    /** Twilio's endpoint for sending a message from an account. */
    protected const TWILIO_URL = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

    protected string $driver;

    public function __construct(?string $driver = null)
    {
        $this->driver = $driver ?? (string)env('SMS_TRANSPORT', 'log');
    }

    /**
     * @param string $to Recipient in E.164, e.g. +61415555365.
     * @param string $message The message body.
     * @return bool Whether the message was handed off successfully.
     */
    public function send(string $to, string $message): bool
    {
        switch ($this->driver) {
            case 'none':
                return false;

            case 'twilio':
                return $this->sendWithTwilio($to, $message);

            case 'log':
            default:
                /*
                 * Logged at info so it shows up in debug.log during
                 * development without needing a phone in the loop.
                 */
                Log::info(sprintf('[SMS -> %s] %s', $to, $message));

                return true;
        }
    }

    /**
     * True when messages are only being logged rather than delivered, so the
     * UI can say so instead of implying a text is on its way.
     *
     * @return bool
     */
    public function isSimulated(): bool
    {
        return $this->driver !== 'twilio';
    }

    /**
     * Hands the message to Twilio.
     *
     * TWILIO_FROM is either a Twilio phone number in E.164, or a Messaging
     * Service id (starting "MG"), which Twilio then sends from whichever
     * sender the service holds.
     *
     * Twilio answering is only Twilio accepting the message - it can still
     * fail to reach the phone afterwards, and that shows up in Twilio's
     * console rather than here.
     *
     * @param string $to
     * @param string $message
     * @return bool
     */
    protected function sendWithTwilio(string $to, string $message): bool
    {
        $sid = (string)env('TWILIO_ACCOUNT_SID', '');
        $token = (string)env('TWILIO_AUTH_TOKEN', '');
        $from = (string)env('TWILIO_FROM', '');

        if ($sid === '' || $token === '' || $from === '') {
            Log::error('SMS not sent: SMS_TRANSPORT is twilio but TWILIO_ACCOUNT_SID, '
                . 'TWILIO_AUTH_TOKEN or TWILIO_FROM is not set.');

            return false;
        }

        $data = ['To' => $to, 'Body' => $message];

        if (str_starts_with($from, 'MG')) {
            $data['MessagingServiceSid'] = $from;
        } else {
            $data['From'] = $from;
        }

        try {
            $response = (new Client(['timeout' => 15]))->post(
                sprintf(self::TWILIO_URL, rawurlencode($sid)),
                $data,
                ['auth' => ['username' => $sid, 'password' => $token]],
            );
        } catch (Throwable $e) {
            Log::error('SMS not sent: could not reach Twilio - ' . $e->getMessage());

            return false;
        }

        if (!$response->isSuccess()) {
            /*
             * Twilio's own reason, e.g. "The 'To' number is not a valid phone
             * number" or, on a trial account, that the number has not been
             * verified. Never the message itself: it holds a working code.
             */
            $error = $response->getJson();

            Log::error(sprintf(
                'SMS not sent: Twilio answered %d - %s',
                $response->getStatusCode(),
                is_array($error) ? (string)($error['message'] ?? '') : '',
            ));

            return false;
        }

        return true;
    }
}
