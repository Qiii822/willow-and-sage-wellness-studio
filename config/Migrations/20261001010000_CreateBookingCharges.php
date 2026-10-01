<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * What the studio expects to be paid for a booking, and whether it is owed.
 *
 * `bookings` and `payments` are wiped and reloaded from Square on every
 * sync, so anything the studio decides about a booking's bill has to live
 * somewhere a sync does not touch. Square has nowhere to keep the menu
 * price a payment was compared against, or a note that a customer left
 * without paying - so they are kept here, keyed on the Square booking id
 * that survives a sync.
 *
 * Also records how much of a payment has been refunded, which is what
 * undoing a payment recorded by mistake does in Square.
 */
class CreateBookingCharges extends BaseMigration
{
    public function up(): void
    {
        $this->table('booking_charges', [
            'id' => false,
            'primary_key' => ['id'],
        ])
            ->addColumn('id', 'uuid')
            ->addColumn('square_booking_id', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('price_cents', 'integer', [
                'null' => true,
                'comment' => 'Menu price of the booking when it was paid or marked unpaid, in cents (AUD).',
            ])
            ->addColumn('unpaid', 'boolean', [
                'null' => false,
                'default' => false,
                'comment' => 'Marked by staff as owing - shown in red until a payment is recorded.',
            ])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['square_booking_id'], ['unique' => true])
            ->addIndex(['unpaid'])
            ->create();

        $this->table('payments')
            ->addColumn('refunded_amount', 'integer', [
                'null' => true,
                'after' => 'total_amount',
                'comment' => 'Amount refunded so far, in cents (AUD). A fully refunded payment was undone.',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('payments')->removeColumn('refunded_amount')->update();
        $this->table('booking_charges')->drop()->save();
    }
}
