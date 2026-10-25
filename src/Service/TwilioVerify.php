<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;
use Cake\Log\Log;
use Throwable;

/**
 * Sign-in codes made, texted and checked by Twilio Verify.
 *
 * Used for the My bookings code whenever TWILIO_VERIFY_SERVICE_SID is set.
 * Twilio generates a fresh random code for each request, sends it in its
 * own message ("Your Willow and Sage verification code is: ..."), and says
 * whether a typed code is right - so the code never passes through this
 * site at all.
 *
 * It is also the one way to text a real code from a Twilio trial account,
 * which refuses any message body but Twilio's own templates - and the
 * trial's own "verification code" template is the same six digits every
 * time, so it proves nothing.
 */
class TwilioVerify
{
    /** The code was right. */
    public const APPROVED = 'approved';

    /** The code was wrong, and the one sent can still be tried. */
    public const WRONG = 'wrong';

    /**
     * The code sent can no longer be used at all - too many wrong tries,
     * already used, or expired - so even the right one would be refused
     * now, and the only way on is a new code.
     */
    public const SPENT = 'spent';

    /** The text was sent. */
    public const SENT = 'sent';

    /**
     * Twilio refused to send: this number has had as many codes as it may
     * in the last ten minutes. Waiting is the only fix.
     */
    public const TOO_MANY = 'too_many';

    /** Twilio refused for any other reason, or could not be reached. */
    public const FAILED = 'failed';

    /** How long Twilio holds a number to its send limit. */
    public const SEND_WINDOW_MINUTES = 10;

    protected const BASE_URL = 'https://verify.twilio.com/v2/Services/%s/%s';

    /**
     * Whether codes should go through Verify on this copy of the site.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (string)env('TWILIO_VERIFY_SERVICE_SID', '') !== ''
            && (string)env('TWILIO_ACCOUNT_SID', '') !== ''
            && (string)env('TWILIO_AUTH_TOKEN', '') !== '';
    }

    /**
     * Texts a code to this number - the same one again if Twilio still has
     * one waiting for it, which is how Verify works.
     *
     * Twilio limits how many texts a number gets in ten minutes and answers
     * 429 past that, which is worth telling apart: "try again in a minute"
     * would only have the customer burn through more refusals.
     *
     * @param string $to E.164, e.g. +61412345678.
     * @return string One of SENT, TOO_MANY or FAILED.
     */
    public function send(string $to): string
    {
        [$status] = $this->post('Verifications', ['To' => $to, 'Channel' => 'sms']);

        if ($status === 429) {
            return self::TOO_MANY;
        }

        return $status >= 200 && $status < 300 ? self::SENT : self::FAILED;
    }

    /**
     * Whether this is the code Twilio last sent to this number.
     *
     * Twilio keeps one code per number for ten minutes - asking again
     * resends the same one - allows only a few wrong tries at it, and lets
     * it be used once. A wrong try comes back "pending"; once the tries are
     * used up (429) or the code is spent or expired (404) Twilio refuses
     * even the right code, which is a different thing to tell the customer.
     *
     * @param string $to E.164.
     * @param string $code
     * @return string One of APPROVED, WRONG or SPENT.
     */
    public function check(string $to, string $code): string
    {
        $code = trim($code);

        if ($code === '') {
            return self::WRONG;
        }

        [$status, $body] = $this->post('VerificationCheck', ['To' => $to, 'Code' => $code]);

        if ($status === 404 || $status === 429) {
            return self::SPENT;
        }

        return ($body['status'] ?? null) === 'approved' ? self::APPROVED : self::WRONG;
    }

    /**
     * @param string $endpoint
     * @param array<string, string> $data
     * @return array{0: int, 1: array<string, mixed>} The HTTP status - 0 when
     *  Twilio could not be reached - and its answer.
     */
    protected function post(string $endpoint, array $data): array
    {
        $sid = (string)env('TWILIO_ACCOUNT_SID', '');

        try {
            $response = (new Client(['timeout' => 15]))->post(
                sprintf(self::BASE_URL, rawurlencode((string)env('TWILIO_VERIFY_SERVICE_SID', '')), $endpoint),
                $data,
                ['auth' => ['username' => $sid, 'password' => (string)env('TWILIO_AUTH_TOKEN', '')]],
            );
        } catch (Throwable $e) {
            Log::error('Twilio Verify unreachable: ' . $e->getMessage());

            return [0, []];
        }

        $body = $response->getJson();
        $body = is_array($body) ? $body : [];

        if (!$response->isSuccess()) {
            /*
             * Warning, not error: the commonest refusals - a code used up or
             * expired - are the customer's doing, and the page tells them
             * what to do about it.
             */
            Log::warning(sprintf(
                'Twilio Verify %s answered %d - %s',
                $endpoint,
                $response->getStatusCode(),
                (string)($body['message'] ?? ''),
            ));
        }

        return [$response->getStatusCode(), $body];
    }
}
