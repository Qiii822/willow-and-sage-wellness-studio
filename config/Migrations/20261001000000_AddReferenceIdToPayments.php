<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Lets a payment be traced back to the booking it settled.
 *
 * Square has no link between a payment and a booking, but a payment can
 * carry a free-text reference id. Payments recorded from the booking page
 * put the Square booking id there, and this column keeps it through the
 * sync so the booking can show it has been paid.
 */
class AddReferenceIdToPayments extends BaseMigration
{
    public function up(): void
    {
        $this->table('payments')
            ->addColumn('reference_id', 'string', [
                'limit' => 255,
                'null' => true,
                'after' => 'order_id',
                'comment' => "Square's reference_id. For payments recorded from a booking, the Square booking id.",
            ])
            ->addIndex(['reference_id'])
            ->update();
    }

    public function down(): void
    {
        $this->table('payments')
            ->removeIndex(['reference_id'])
            ->removeColumn('reference_id')
            ->update();
    }
}
