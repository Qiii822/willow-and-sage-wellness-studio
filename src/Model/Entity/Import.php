<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Import Entity
 *
 * One row per sync run against Square (OAuth callback, cron, manual, or
 * webhook).
 *
 * @property string $id
 * @property string $identity_id
 * @property string $resource
 * @property string $source
 * @property string $status
 * @property \Cake\I18n\DateTime $started_at
 * @property \Cake\I18n\DateTime|null $finished_at
 * @property string|null $error_message
 *
 * @property \App\Model\Entity\Identity $identity
 */
class Import extends Entity
{
    protected array $_accessible = [
        'identity_id' => true,
        'resource' => true,
        'source' => true,
        'status' => true,
        'started_at' => true,
        'finished_at' => true,
        'error_message' => true,
        'identity' => true,
    ];
}
