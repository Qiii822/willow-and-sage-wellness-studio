<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * BookingCharge Entity
 *
 * @property string $id
 * @property string $square_booking_id
 * @property int|null $price_cents Menu price when paid or marked unpaid.
 * @property bool $unpaid
 * @property bool $cancelled_by_staff Cancelled from the dashboard, whatever
 *  Square recorded - it files every cancellation under the customer.
 * @property \Cake\I18n\DateTime $created_at
 * @property \Cake\I18n\DateTime $updated_at
 */
class BookingCharge extends Entity
{
    protected array $_accessible = [
        'square_booking_id' => true,
        'price_cents' => true,
        'unpaid' => true,
        'cancelled_by_staff' => true,
    ];
}
