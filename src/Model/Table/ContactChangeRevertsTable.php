<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class ContactChangeRevertsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('contact_change_reverts');
        $this->setPrimaryKey('id');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * How long after an email change a password reset still goes to the
     * address that was replaced.
     *
     * This is the window the warning email needs to be useful: if a reset
     * could be sent to a freshly set address immediately, someone who had
     * changed it could take the account over before the real owner had read
     * anything.
     */
    public const COOLDOWN_HOURS = 24;

    /**
     * The address to send a password reset to, when a recent email change
     * means it should not be the current one.
     *
     * @param string $userId
     * @return string|null The previous address, or null when there is no
     *   recent change and the account's own address should be used.
     */
    public function addressDuringCooldown(string $userId): ?string
    {
        $recent = $this->find()
            ->where([
                'user_id' => $userId,
                'field' => 'email',
                // A reverted change is already undone; its window is over.
                'used_at IS' => null,
                'created_at >' => new \DateTimeImmutable('-' . self::COOLDOWN_HOURS . ' hours'),
            ])
            ->orderBy(['created_at' => 'DESC'])
            ->first();

        return $recent->previous_value ?? null;
    }

    /**
     * The usable revert record for a raw token, if there is one.
     *
     * @param string $token The token exactly as it appeared in the link.
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function findByToken(string $token)
    {
        if ($token === '') {
            return null;
        }

        return $this->find()
            ->contain(['Users'])
            ->where([
                'token_hash' => hash('sha256', $token),
                'used_at IS' => null,
                'expires_at >' => new \DateTimeImmutable(),
            ])
            ->first();
    }
}
