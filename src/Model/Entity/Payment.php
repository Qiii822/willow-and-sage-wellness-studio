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
 * @property int|null $refunded_amount Amount refunded so far, in cents.
 * @property-read bool $is_undone Fully refunded - for a booking payment, undone.
 * @property-read bool $is_paid Went through and has not been undone.
 * @property string|null $source_type
 * @property string|null $square_customer_id
 * @property string|null $order_id
 * @property string|null $reference_id For payments recorded from a booking, the Square booking id.
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
        'refunded_amount' => true,
        'source_type' => true,
        'square_customer_id' => true,
        'order_id' => true,
        'reference_id' => true,
        'receipt_number' => true,
        'receipt_url' => true,
        'note' => true,
        'square_created_at' => true,
        'square_updated_at' => true,
        'created_at' => true,
        'updated_at' => true,
    ];

    /** Square statuses for a payment that went through. */
    public const PAID_STATUSES = ['completed', 'approved'];

    /**
     * Fully refunded. Undoing a payment recorded by mistake refunds all of
     * it, since Square will not delete or cancel a completed payment.
     *
     * @return bool
     */
    protected function _getIsUndone(): bool
    {
        return $this->amount > 0 && (int)$this->refunded_amount >= $this->amount;
    }

    /**
     * Went through and has not been undone.
     *
     * @return bool
     */
    protected function _getIsPaid(): bool
    {
        return in_array($this->status, self::PAID_STATUSES, true) && !$this->is_undone;
    }
}
