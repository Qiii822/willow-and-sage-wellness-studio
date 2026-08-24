<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateBookings extends BaseMigration
{
    public function up(): void
    {
        $this->table('bookings', [
            'id' => false,
            'primary_key' => ['id'],
        ])
            ->addColumn('id', 'uuid')
            ->addColumn('square_booking_id', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('status', 'enum', [
                'values' => ['pending', 'cancelled_by_customer', 'cancelled_by_seller', 'declined', 'accepted', 'no_show'],
                'null' => false,
            ])
            ->addColumn('location_id', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('square_customer_id', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('start_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('customer_note', 'text', [
                'null' => true,
            ])
            ->addColumn('seller_note', 'text', [
                'null' => true,
            ])
            ->addColumn('appointment_segments', 'text', [
                'null' => true,
                'comment' => 'JSON array of {duration_minutes, service_variation_id, service_variation_version, team_member_id}',
            ])
            ->addColumn('square_version', 'integer', [
                'null' => false,
            ])
            ->addColumn('square_updated_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('square_created_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
            ])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['square_booking_id'], ['unique' => true])
            ->addIndex(['square_customer_id'])
            ->addIndex(['status'])
            ->addIndex(['start_at'])
            ->create();
    }

    public function down(): void
    {
        $this->table('bookings')->drop()->save();
    }
}
