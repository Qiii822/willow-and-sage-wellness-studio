<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\User;
use App\Utility\PhoneNumber;
use Cake\Log\Log;
use App\Service\MailerFactory;
use Cake\Mailer\Mailer;
use Cake\ORM\TableRegistry;

/**
 * Tells the previous address about a contact change, and lets it be undone.
 *
 * This is the detection half of contact security: the current password
 * authorises a change and a code to the new address proves it is reachable,
 * but neither helps if an attacker already has both. The old address is
 * always told what happened and always given a way back, which turns a
 * silent account takeover into something the owner can see and reverse.
 *
 * Deliberately a notice rather than a gate. Requiring the old address to
 * approve the change would strand anyone whose old inbox is exactly what
 * they have lost access to.
 */
class ContactChangeRevertService
{
    /** Long enough that a warning read a fortnight later is still useful. */
    public const TTL_DAYS = 30;

    protected \Cake\ORM\Table $revertsTable;
    protected \Cake\ORM\Table $usersTable;

    public function __construct()
    {
        $this->revertsTable = TableRegistry::getTableLocator()->get('ContactChangeReverts');
        $this->usersTable = TableRegistry::getTableLocator()->get('Users');
    }

    /**
     * Records an undo option and warns the address that was replaced.
     *
     * @param \App\Model\Entity\User $user The account, already updated.
     * @param string $field 'email' or 'phone'.
     * @param string $previousValue The value that was just replaced.
     * @param string $newValue What it became.
     * @param string $notifyEmail Where to send the warning. For a phone
     *   change this is the account's email, since there is no SMS provider.
     * @return void
     */
    public function recordAndNotify(
        User $user,
        string $field,
        string $previousValue,
        string $newValue,
        string $notifyEmail
    ): void {
        $token = bin2hex(random_bytes(32));

        $entity = $this->revertsTable->newEntity([
            'user_id' => $user->id,
            'field' => $field,
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'token_hash' => hash('sha256', $token),
            'expires_at' => new \DateTimeImmutable('+' . self::TTL_DAYS . ' days'),
            'created_at' => new \DateTimeImmutable(),
        ]);

        if (!$this->revertsTable->save($entity)) {
            Log::error('Could not record a contact change revert for user ' . $user->id);

            return;
        }

        /*
         * A failed warning must not undo a change the owner asked for, so
         * this only logs. The undo record stays valid either way.
         */
        try {
            $mailer = MailerFactory::create();
            $mailer
                ->setEmailFormat('both')
                ->setTo($notifyEmail)
                ->setSubject('Your contact details were changed')
                ->setViewVars([
                    'first_name' => $user->first_name,
                    'field' => $field === 'phone' ? 'mobile number' : 'email address',
                    'previous_value' => $this->present($field, $previousValue),
                    'new_value' => $this->mask($field, $newValue),
                    'token' => $token,
                    'days' => self::TTL_DAYS,
                ])
                ->viewBuilder()
                ->setTemplate('contact_changed');

            $mailer->deliver();
        } catch (\Throwable $e) {
            Log::error('Contact change warning email failed: ' . $e->getMessage());
        }
    }

    /**
     * Puts the previous value back and closes off the attacker's route in.
     *
     * A password reset is issued at the same time: whoever made the change
     * had the account's password, so restoring the address alone would leave
     * them able to simply do it again.
     *
     * @param \Cake\Datasource\EntityInterface $revert A record from findByToken().
     * @return array{ok: bool, message: string}
     */
    public function revert($revert): array
    {
        /** @var \App\Model\Entity\User $user */
        $user = $revert->user ?? $this->usersTable->get($revert->user_id);

        $user->set($revert->field, $revert->previous_value);

        if (!$this->usersTable->save($user)) {
            return [
                'ok' => false,
                'message' => 'We could not restore that detail. Please contact the studio.',
            ];
        }

        $revert->used_at = new \DateTimeImmutable();
        $this->revertsTable->save($revert);

        // Any code still outstanding belongs to the change being rejected.
        TableRegistry::getTableLocator()
            ->get('VerificationCodes')
            ->deleteAll(['user_id' => $user->id]);

        return [
            'ok' => true,
            'message' => 'Your ' . ($revert->field === 'phone' ? 'mobile number' : 'email address')
                . ' has been restored.',
        ];
    }

    /**
     * Formats a value for display in the warning email.
     */
    protected function present(string $field, string $value): string
    {
        return $field === 'phone'
            ? (string)PhoneNumber::format($value)
            : $value;
    }

    /**
     * Partially hides the new value.
     *
     * The warning goes to an address that may no longer be controlled by the
     * account holder, so it says enough to recognise an unexpected change
     * without handing over a full address or number.
     */
    protected function mask(string $field, string $value): string
    {
        // Plain ASCII, so the mask survives any mail client's encoding.
        if ($field === 'phone') {
            return str_repeat('*', max(0, strlen($value) - 3)) . substr($value, -3);
        }

        $at = strpos($value, '@');

        if ($at === false || $at < 1) {
            return '***';
        }

        return substr($value, 0, 1)
            . str_repeat('*', max(1, $at - 1))
            . substr($value, $at);
    }
}

