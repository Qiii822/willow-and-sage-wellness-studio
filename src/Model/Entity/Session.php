<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Session Entity
 *
 * @property string $id
 * @property string $identity_id
 * @property \Cake\I18n\DateTime $created_at
 * @property \Cake\I18n\DateTime|null $expires_at
 * @property bool $revoked
 * @property \Cake\I18n\DateTime|null $revoked_at
 *
 * @property \App\Model\Entity\Identity $identity
 */
class Session extends Entity
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
        'identity_id' => true,
        'created_at' => true,
        'expires_at' => true,
        'revoked' => true,
        'revoked_at' => true,
        'identity' => true,
    ];
}
