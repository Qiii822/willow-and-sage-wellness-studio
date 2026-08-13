<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Customer Entity
 *
 * Local mirror of a Square customer, kept in sync by CustomerSyncService.
 * Not to be confused with \Square\Types\Customer, the SDK's representation
 * of the same data as returned directly from Square's API.
 *
 * @property string $id
 * @property string $square_customer_id
 * @property string|null $given_name
 * @property string|null $family_name
 * @property string|null $email_address
 * @property string|null $phone_number
 * @property int $square_version
 * @property \Cake\I18n\DateTime|null $square_updated_at
 * @property \Cake\I18n\DateTime $square_created_att
 * @property \Cake\I18n\DateTime|null $deleted_at
 * @property \Cake\I18n\DateTime $created_at
 * @property \Cake\I18n\DateTime $updated_at
 */
class Customer extends Entity
{
    protected array $_accessible = [
        'square_customer_id' => true,
        'given_name' => true,
        'family_name' => true,
        'email_address' => true,
        'phone_number' => true,
        'square_version' => true,
        'square_updated_at' => true,
        'square_created_at' => true,
        'deleted_at' => true,
        'created_at' => true,
        'updated_at' => true,
    ];
}
