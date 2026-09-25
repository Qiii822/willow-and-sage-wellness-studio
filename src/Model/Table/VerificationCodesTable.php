<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class VerificationCodesTable extends Table
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('verification_codes');
        $this->setPrimaryKey('id');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * The code still awaiting entry for this user and channel, if any.
     *
     * Expired and already-used rows are excluded, so a caller never has to
     * re-check either condition.
     *
     * @param string $userId
     * @param string $channel
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function findPending(string $userId, string $channel)
    {
        return $this->find()
            ->where([
                'user_id' => $userId,
                'channel' => $channel,
                'consumed_at IS' => null,
                'expires_at >' => new \DateTimeImmutable(),
            ])
            ->orderBy(['created_at' => 'DESC'])
            ->first();
    }

    /**
     * Drops any outstanding codes for a user and channel.
     *
     * Called before issuing a new one so an older code cannot still be used
     * to confirm a value the person has since changed their mind about.
     *
     * @param string $userId
     * @param string $channel
     * @return void
     */
    public function clearPending(string $userId, string $channel): void
    {
        $this->deleteAll([
            'user_id' => $userId,
            'channel' => $channel,
            'consumed_at IS' => null,
        ]);
    }
}
