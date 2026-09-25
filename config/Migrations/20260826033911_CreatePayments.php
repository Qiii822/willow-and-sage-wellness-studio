<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreatePayments extends BaseMigration
{
    public function up(): void
    {
        $this->table('payments', [
            'id' => false,
            'primary_key' => ['id'],
        ])
            ->addColumn('id', 'uuid')
            ->addColumn('square_payment_id', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('status', 'enum', [
                'values' => ['approved', 'pending', 'completed', 'canceled', 'failed'],
                'null' => false,
            ])
            ->addColumn('amount', 'integer', [
                'null' => false,
                'comment' => 'Amount charged, in the smallest denomination of currency (e.g. cents). Currency is always AUD - not stored.',
            ])
            ->addColumn('total_amount', 'integer', [
                'null' => true,
                'comment' => 'Amount including tip, in the smallest denomination of currency. Currency is always AUD - not stored.',
            ])
            ->addColumn('source_type', 'string', [
                'limit' => 50,
                'null' => true,
                'comment' => "Square's payment source, e.g. CARD, CASH, EXTERNAL - not constrained to an enum since Square's list of source types can grow.",
            ])
            ->addColumn('square_customer_id', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'The Square customer this payment is for - not a local FK, since the customers table is fully wiped and reimported on every sync.',
            ])
            ->addColumn('order_id', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('receipt_number', 'string', [
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('receipt_url', 'string', [
                'limit' => 1024,
                'null' => true,
            ])
            ->addColumn('note', 'text', [
                'null' => true,
            ])
            ->addColumn('square_created_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('square_updated_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
            ])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['square_payment_id'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['square_customer_id'])
            ->addIndex(['order_id'])
            ->addIndex(['square_created_at'])
            ->create();
    }

    public function down(): void
    {
        $this->table('payments')->drop()->save();
    }
}
