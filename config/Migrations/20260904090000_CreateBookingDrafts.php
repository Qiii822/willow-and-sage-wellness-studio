<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * A booking a customer is part way through making.
 *
 * The flow spans several pages - choose treatments, choose a time, enter
 * details - and the customer is not signed in, so there is nowhere else to
 * keep what they have chosen. Holding it in the session instead would lose
 * it whenever the session rotates, and would leave nothing to look at when
 * someone reports that booking failed halfway.
 *
 * A draft becomes a real booking in Square at the last step. It is kept
 * afterwards, holding the Square booking id, so the discount code that was
 * used stays attached to something - Square has nowhere to record it.
 */
class CreateBookingDrafts extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('booking_drafts', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            /*
             * What the customer is shown in their confirmation and uses to
             * come back to a draft. Unguessable, because it is the only
             * thing standing between a stranger and someone else's booking.
             */
            ->addColumn('token', 'char', ['limit' => 64, 'null' => false])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'choosing',
                'comment' => 'choosing, timing, details, confirmed, failed',
            ])
            /*
             * The chosen treatments, as the ids Square needs plus enough
             * detail to show a summary without another catalogue call.
             */
            ->addColumn('items', 'text', ['null' => true, 'comment' => 'JSON'])
            ->addColumn('start_at', 'datetime', ['null' => true, 'comment' => 'UTC'])
            ->addColumn('segments', 'text', ['null' => true, 'comment' => 'JSON, from the chosen slot'])
            ->addColumn('customer_name', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('customer_email', 'string', ['limit' => 190, 'null' => true])
            ->addColumn('customer_phone', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('customer_note', 'string', ['limit' => 500, 'null' => true])
            // Money in whole cents, as everywhere else.
            ->addColumn('subtotal_cents', 'integer', ['null' => true])
            ->addColumn('discount_code', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('discount_cents', 'integer', ['null' => true])
            ->addColumn('total_cents', 'integer', ['null' => true])
            // Filled once Square has accepted it.
            ->addColumn('square_booking_id', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('square_customer_id', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('failure_reason', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['square_booking_id'])
            ->addIndex(['customer_phone'])
            ->create();
    }
}
