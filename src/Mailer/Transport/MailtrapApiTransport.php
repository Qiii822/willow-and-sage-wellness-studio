<?php
declare(strict_types=1);

namespace App\Mailer\Transport;

use Cake\Http\Client;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Message;
use Cake\Network\Exception\SocketException;

/**
 * Sends through Mailtrap's HTTP API instead of SMTP.
 *
 * Two reasons this exists rather than everyone using SMTP:
 *
 * - The studio owner is handed one thing by Mailtrap, an API token, and
 *   asking a non-technical person to know it doubles as an SMTP password
 *   under the username "api" is not reasonable.
 * - Plenty of hosts block outbound SMTP ports. This goes over HTTPS on 443,
 *   which is rarely filtered.
 *
 * Only the fields this application actually sends are mapped: from, to,
 * subject, and the text and HTML bodies.
 */
class MailtrapApiTransport extends AbstractTransport
{
    public const ENDPOINT = 'https://send.api.mailtrap.io/api/send';

    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'token' => null,
        'endpoint' => self::ENDPOINT,
        'timeout' => 30,
    ];

    /**
     * @param \Cake\Mailer\Message $message
     * @return array<string, string>
     * @throws \Cake\Network\Exception\SocketException When the API rejects it.
     */
    public function send(Message $message): array
    {
        $token = (string)$this->getConfig('token');

        if ($token === '') {
            throw new SocketException('No Mailtrap API token is configured.');
        }

        $payload = $this->buildPayload($message);

        $client = new Client(['timeout' => (int)$this->getConfig('timeout')]);

        $response = $client->post(
            (string)$this->getConfig('endpoint'),
            json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}',
            [
                'type' => 'json',
                'headers' => [
                    'Api-Token' => $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]
        );

        if (!$response->isOk()) {
            throw new SocketException($this->describeFailure($response));
        }

        return [
            'headers' => '',
            'message' => (string)$response->getStringBody(),
        ];
    }

    /**
     * Maps a Cake message onto Mailtrap's JSON shape.
     *
     * @param \Cake\Mailer\Message $message
     * @return array<string, mixed>
     */
    protected function buildPayload(Message $message): array
    {
        $payload = [
            'from' => $this->firstAddress($message->getFrom()),
            'to' => $this->addressList($message->getTo()),
            'subject' => $message->getSubject(),
        ];

        foreach ([
            'cc' => $message->getCc(),
            'bcc' => $message->getBcc(),
        ] as $key => $addresses) {
            if ($addresses) {
                $payload[$key] = $this->addressList($addresses);
            }
        }

        /*
         * getBodyText()/getBodyHtml() return content for the formats the
         * message was built with, so a text-only message sends no empty html
         * key and vice versa.
         */
        $format = $message->getEmailFormat();

        if ($format !== Message::MESSAGE_HTML) {
            $payload['text'] = $message->getBodyText();
        }

        if ($format !== Message::MESSAGE_TEXT) {
            $payload['html'] = $message->getBodyHtml();
        }

        return $payload;
    }

    /**
     * @param array<string, string> $addresses Email => name, as Cake stores them.
     * @return array<string, string>
     */
    protected function firstAddress(array $addresses): array
    {
        foreach ($addresses as $email => $name) {
            $entry = ['email' => $email];

            // Cake uses the address as the name when none was given.
            if ($name !== '' && $name !== $email) {
                $entry['name'] = $name;
            }

            return $entry;
        }

        return [];
    }

    /**
     * @param array<string, string> $addresses
     * @return array<int, array<string, string>>
     */
    protected function addressList(array $addresses): array
    {
        $list = [];

        foreach ($addresses as $email => $name) {
            $entry = ['email' => $email];

            if ($name !== '' && $name !== $email) {
                $entry['name'] = $name;
            }

            $list[] = $entry;
        }

        return $list;
    }

    /**
     * Turns an API error into something worth showing an administrator.
     *
     * @param \Cake\Http\Client\Response $response
     * @return string
     */
    protected function describeFailure($response): string
    {
        $status = $response->getStatusCode();
        $body = $response->getJson();

        // Mailtrap reports problems as {"errors": ["...", "..."]}.
        if (is_array($body) && !empty($body['errors'])) {
            $errors = is_array($body['errors'])
                ? implode('; ', array_map('strval', $body['errors']))
                : (string)$body['errors'];

            // A bare "Unauthorized" needs the explanation below, not echoing.
            if ($status !== 401) {
                return sprintf('Mailtrap rejected the message (%d): %s', $status, $errors);
            }
        }

        /*
         * Mailtrap answers "unauthorised sender domain" with the same bare
         * 401 it uses for a bad token, which sends people off checking the
         * token when the sending address is what it objects to. Both causes
         * are named so the reader can tell them apart.
         */
        if ($status === 401) {
            return 'Mailtrap returned 401 Unauthorized. Either the API token is wrong, '
                . 'or - more often - the "Send emails from" address is not one your '
                . 'Mailtrap account is allowed to send from. On a free account that '
                . 'must be the demo address, not your own.';
        }

        return sprintf(
            'Mailtrap returned %d: %s',
            $status,
            substr((string)$response->getStringBody(), 0, 200)
        );
    }
}
