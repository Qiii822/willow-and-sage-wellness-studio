<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Remembers that a cancellation came from the dashboard.
 *
 * Square will not record it. Its CancelBooking endpoint files a
 * cancellation under the customer unless the call carries seller-level
 * permissions, and those need an Appointments Plus or Premium subscription
 * - asking for the scope without one takes down every booking write with a
 * 403, so the scope is deliberately not requested. The result is that a
 * cancellation Wendy makes herself comes back from Square saying the
 * customer did it.
 *
 * So the studio's own side of it is kept here. `booking_charges` is the
 * right home: it is already the table of things this site knows about a
 * booking that Square does not keep, keyed on the Square booking id so it
 * survives the sync emptying and rebuilding the bookings table.
 */
class AddCancelledByStaffToBookingCharges extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('booking_charges')
            ->addColumn('cancelled_by_staff', 'boolean', [
                'null' => false,
                'default' => false,
                'after' => 'unpaid',
                'comment' => 'The studio cancelled this from the dashboard, whatever Square recorded.',
            ])
            ->update();
    }
}
