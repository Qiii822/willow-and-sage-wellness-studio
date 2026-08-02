<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Identity Entity
 *
 * @property string $id
 * @property string $merchant_id
 * @property string|null $tokens
 * @property \Cake\I18n\DateTime $created_at
 * @property \Cake\I18n\DateTime $expires_at
 * @property \Cake\I18n\DateTime|null $refreshed_at
 * @property bool $revoked
 * @property \Cake\I18n\DateTime|null $revoked_at
 *
 * @property \App\Model\Entity\Session[] $sessions
 */
class Identity extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'merchant_id' => true,
        'tokens' => true,
        'created_at' => true,
        'expires_at' => true,
        'refreshed_at' => true,
        'revoked' => true,
        'revoked_at' => true,
        'sessions' => true,
    ];
}
