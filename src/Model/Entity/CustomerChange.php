<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * CustomerChange Entity
 *
 * Audit log of an individual customer create/update/delete that occurred
 * during an Import. `square_customer_id` is stored directly rather than
 * as a local FK to customers.id, so this row stays valid even if the
 * customer it refers to is later hard-deleted.
 *
 * @property string $id
 * @property string $import_id
 * @property string $square_customer_id
 * @property string $action
 * @property string|null $diff
 * @property string|null $event_id
 * @property \Cake\I18n\DateTime $created_at
 *
 * @property \App\Model\Entity\Import $import
 */
class CustomerChange extends Entity
{
    protected array $_accessible = [
        'import_id' => true,
        'square_customer_id' => true,
        'action' => true,
        'diff' => true,
        'event_id' => true,
        'created_at' => true,
        'import' => true,
    ];
}
