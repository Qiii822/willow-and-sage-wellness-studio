<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Log\Log;

/**
 * Sends SMS, or pretends to.
 *
 * This app has no SMS account. Rather than let that block the verification
 * flow, delivery goes through here: the `log` driver writes the message to
 * the application log so the whole journey can be built and tested, and a
 * real provider slots in behind the same `send()` call later.
 *
 * Pick the driver with SMS_TRANSPORT in config/.env:
 *   log     - default; writes to logs/debug.log and never leaves the machine
 *   none    - reports failure, to exercise the "could not send" path
 *
 * Adding a provider means one more branch in send() plus its credentials in
 * the environment; nothing that calls this class has to change.
 */
class SmsSender
{
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
}
