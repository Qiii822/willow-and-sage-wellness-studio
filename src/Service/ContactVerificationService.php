<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\User;
use App\Model\Table\VerificationCodesTable;
use App\Utility\PhoneNumber;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Log\Log;
use App\Service\MailerFactory;
use Cake\Mailer\Mailer;
use Cake\ORM\TableRegistry;

/**
 * Confirms that a new email address or mobile number actually reaches the
 * person before it replaces the one on their account.
 *
 * The pending value is held on the verification row, not on the user, so an
 * unconfirmed address is never the account's contact detail - which matters
 * because that address is where a password reset would be sent.
 */
class ContactVerificationService
{
    /** Codes are short enough to retype, and rate limited below. */
    public const CODE_LENGTH = 6;

    public const TTL_MINUTES = 10;

    /** Wrong guesses allowed before the code is thrown away. */
    public const MAX_ATTEMPTS = 5;

    protected \Cake\ORM\Table $codesTable;
    protected \Cake\ORM\Table $usersTable;
    protected SmsSender $sms;

    public function __construct(?SmsSender $sms = null)
    {
        $this->codesTable = TableRegistry::getTableLocator()->get('VerificationCodes');
        $this->usersTable = TableRegistry::getTableLocator()->get('Users');
        $this->sms = $sms ?? new SmsSender();
    }

    /**
     * Issues a code for a pending contact change and sends it to the new
     * address or number.
     *
     * @param \App\Model\Entity\User $user Whose account is changing.
     * @param string $channel VerificationCodesTable::CHANNEL_*
     * @param string $destination The new email, or a number in E.164.
     * @return bool False if the message could not be handed to its transport.
     */
    public function startVerification(User $user, string $channel, string $destination): bool
    {
        // A previous code must not survive to confirm a superseded value.
        $this->codesTable->clearPending($user->id, $channel);

        $code = $this->generateCode();

        $entity = $this->codesTable->newEntity([
            'user_id' => $user->id,
            'channel' => $channel,
            'destination' => $destination,
            'code_hash' => (new DefaultPasswordHasher())->hash($code),
            'attempts' => 0,
            'expires_at' => new \DateTimeImmutable('+' . self::TTL_MINUTES . ' minutes'),
            'created_at' => new \DateTimeImmutable(),
        ]);

        if (!$this->codesTable->save($entity)) {
            return false;
        }

        $sent = $channel === VerificationCodesTable::CHANNEL_SMS
            ? $this->sendSms($destination, $code)
            : $this->sendEmail($user, $destination, $code);

        if (!$sent) {
            // Nothing arrived, so leave no code lying around to be guessed.
            $this->codesTable->delete($entity);
        }

        return $sent;
    }

    /**
     * Checks a submitted code and, if it matches, writes the pending value
     * onto the account.
     *
     * @param \App\Model\Entity\User $user
     * @param string $channel
     * @param string $submitted The code as typed.
     * @return array{ok: bool, message: string}
     */
    public function confirm(User $user, string $channel, string $submitted): array
    {
        $pending = $this->codesTable->findPending($user->id, $channel);

        if (!$pending) {
            return [
                'ok' => false,
                'message' => 'That code has expired. Request a new one.',
            ];
        }

        $submitted = preg_replace('/\D+/', '', $submitted) ?? '';

        if (!(new DefaultPasswordHasher())->check($submitted, $pending->code_hash)) {
            $pending->attempts += 1;

            if ($pending->attempts >= self::MAX_ATTEMPTS) {
                // Burn it rather than let guessing continue.
                $this->codesTable->delete($pending);

                return [
                    'ok' => false,
                    'message' => 'Too many incorrect codes. Request a new one.',
                ];
            }

            $this->codesTable->save($pending);

            $left = self::MAX_ATTEMPTS - $pending->attempts;

            return [
                'ok' => false,
                'message' => sprintf(
                    'That code is not correct. %d attempt%s left.',
                    $left,
                    $left === 1 ? '' : 's'
                ),
            ];
        }

        $field = $channel === VerificationCodesTable::CHANNEL_SMS ? 'phone' : 'email';

        $user->set($field, $pending->destination);

        if (!$this->usersTable->save($user)) {
            /*
             * Someone else claimed the address or number in the meantime -
             * both columns are unique - so the code stays valid and the
             * person can try a different value.
             */
            return [
                'ok' => false,
                'message' => 'That contact detail is already in use on another account.',
            ];
        }

        $pending->consumed_at = new \DateTimeImmutable();
        $this->codesTable->save($pending);

        return [
            'ok' => true,
            'message' => $channel === VerificationCodesTable::CHANNEL_SMS
                ? 'Your mobile number has been updated.'
                : 'Your email address has been updated.',
        ];
    }

    /**
     * Discards a pending change without applying it.
     *
     * @param \App\Model\Entity\User $user
     * @param string $channel
     * @return void
     */
    public function cancel(User $user, string $channel): void
    {
        $this->codesTable->clearPending($user->id, $channel);
    }

    /**
     * True when SMS is only being logged rather than actually delivered.
     *
     * @return bool
     */
    public function smsIsSimulated(): bool
    {
        return $this->sms->isSimulated();
    }

    /**
     * A zero-padded numeric code.
     *
     * random_int is used rather than rand/mt_rand because this value guards
     * a change of contact details and must not be predictable.
     *
     * @return string
     */
    protected function generateCode(): string
    {
        $max = (10 ** self::CODE_LENGTH) - 1;

        return str_pad(
            (string)random_int(0, $max),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT
        );
    }

    protected function sendEmail(User $user, string $destination, string $code): bool
    {
        try {
            $mailer = MailerFactory::create();
            $mailer
                ->setEmailFormat('both')
                ->setTo($destination)
                ->setSubject('Your verification code')
                ->setViewVars([
                    'first_name' => $user->first_name,
                    'code' => $code,
                    'minutes' => self::TTL_MINUTES,
                ])
                ->viewBuilder()
                ->setTemplate('verification_code');

            return (bool)$mailer->deliver();
        } catch (\Throwable $e) {
            // A misconfigured transport throws rather than returning false.
            Log::error('Verification email failed: ' . $e->getMessage());

            return false;
        }
    }

    protected function sendSms(string $destination, string $code): bool
    {
        return $this->sms->send(
            PhoneNumber::normalise($destination) ?? $destination,
            sprintf(
                'Your Willow and Sage verification code is %s. It expires in %d minutes.',
                $code,
                self::TTL_MINUTES
            )
        );
    }
}

