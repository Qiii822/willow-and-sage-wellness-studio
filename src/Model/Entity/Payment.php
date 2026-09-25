<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Payment Entity
 *
 * Local mirror of a Square payment, kept in sync by PaymentSyncService.
 * Not to be confused with \Square\Types\Payment, the SDK's representation
 * of the same data as returned directly from Square's API.
 *
 * @property string $id
 * @property string $square_payment_id
 * @property string $status
 * @property int $amount
 * @property int|null $total_amount
 * @property string|null $source_type
 * @property string|null $square_customer_id
 * @property string|null $order_id
 * @property string|null $receipt_number
 * @property string|null $receipt_url
 * @property string|null $note
 * @property \Cake\I18n\DateTime|null $square_created_at
 * @property \Cake\I18n\DateTime|null $square_updated_at
 * @property \Cake\I18n\DateTime $created_at
 * @property \Cake\I18n\DateTime $updated_at
 */
class Payment extends Entity
{
    protected array $_accessible = [
        'square_payment_id' => true,
        'status' => true,
        'amount' => true,
        'total_amount' => true,
        'source_type' => true,
        'square_customer_id' => true,
        'order_id' => true,
        'receipt_number' => true,
        'receipt_url' => true,
        'note' => true,
        'square_created_at' => true,
        'square_updated_at' => true,
        'created_at' => true,
        'updated_at' => true,
    ];
}
