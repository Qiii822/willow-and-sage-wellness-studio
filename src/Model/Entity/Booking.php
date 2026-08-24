<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Booking Entity
 *
 * Local mirror of a Square booking, kept in sync by BookingSyncService.
 * Not to be confused with \Square\Types\Booking, the SDK's representation
 * of the same data as returned directly from Square's API.
 *
 * @property string $id
 * @property string $square_booking_id
 * @property string $status
 * @property string|null $location_id
 * @property string|null $square_customer_id
 * @property \Cake\I18n\DateTime|null $start_at
 * @property string|null $customer_note
 * @property string|null $seller_note
 * @property string|null $appointment_segments JSON-encoded array of segments.
 * @property int $square_version
 * @property \Cake\I18n\DateTime|null $square_updated_at
 * @property \Cake\I18n\DateTime|null $square_created_at
 * @property \Cake\I18n\DateTime $created_at
 * @property \Cake\I18n\DateTime $updated_at
 */
class Booking extends Entity
{
    protected array $_accessible = [
        'square_booking_id' => true,
        'status' => true,
        'location_id' => true,
        'square_customer_id' => true,
        'start_at' => true,
        'customer_note' => true,
        'seller_note' => true,
        'appointment_segments' => true,
        'square_version' => true,
        'square_updated_at' => true,
        'square_created_at' => true,
        'created_at' => true,
        'updated_at' => true,
    ];

    /**
     * Decodes the stored JSON appointment segments back into an array.
     *
     * @return array
     */
    protected function _getAppointmentSegmentsArray(): array
    {
        return $this->appointment_segments ? json_decode($this->appointment_segments, true) : [];
    }
}
