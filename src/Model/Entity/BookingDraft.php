<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\I18n\DateTime;
use Cake\ORM\Entity;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A booking being made.
 *
 * @property string $id
 * @property string $token
 * @property string $status
 * @property array<int, array<string, mixed>> $items
 * @property \Cake\I18n\DateTime|null $start_at
 * @property array<int, array<string, mixed>> $segments
 * @property string|null $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property string|null $customer_note
 * @property int|null $subtotal_cents
 * @property string|null $discount_code
 * @property int|null $discount_cents
 * @property int|null $total_cents
 * @property string|null $square_booking_id
 * @property string|null $square_customer_id
 * @property string|null $failure_reason
 */
class BookingDraft extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'customer_name' => true,
        'customer_email' => true,
        'customer_phone' => true,
        'customer_note' => true,
        /*
         * Everything else is set by the controller from what Square
         * returned, never from a submitted form - a chosen slot or a
         * discounted total arriving from the browser would be a price the
         * customer wrote themselves.
         */
        'token' => false,
        'status' => false,
        'items' => false,
        'start_at' => false,
        'segments' => false,
        'team_member_id' => false,
        'subtotal_cents' => false,
        'discount_code' => false,
        'discount_cents' => false,
        'total_cents' => false,
        'square_booking_id' => false,
        'square_customer_id' => false,
        'failure_reason' => false,
    ];

    /**
     * What the customer pays, after any discount.
     *
     * @return int
     */
    protected function _getPayableCents(): int
    {
        return max(0, (int)$this->subtotal_cents - (int)$this->discount_cents);
    }

    /**
     * The chosen time in the studio's own timezone, ready to print.
     *
     * The column is a plain `datetime`, which carries no zone, and it holds
     * the UTC instant Square gave us. Read back, the ORM hands it over
     * labelled with PHP's default zone instead - so converting it directly
     * is a no-op when those two happen to match, and silently wrong when
     * they do not. Re-reading the wall-clock figure as UTC removes the
     * guess: 23:00 stored is 23:00 UTC, whatever the server is set to.
     *
     * @param string $timezone
     * @return string|null
     */
    public function startAtLocal(string $timezone): ?string
    {
        $utc = $this->startAtUtc();

        return $utc?->setTimezone(new DateTimeZone($timezone))->format('D j M Y, g:i a');
    }

    /**
     * The chosen time as the UTC instant it really is.
     *
     * @return \DateTimeImmutable|null
     */
    public function startAtUtc(): ?DateTimeImmutable
    {
        if (!$this->start_at instanceof DateTime) {
            return null;
        }

        return new DateTimeImmutable(
            $this->start_at->format('Y-m-d H:i:s'),
            new DateTimeZone('UTC'),
        );
    }
}
