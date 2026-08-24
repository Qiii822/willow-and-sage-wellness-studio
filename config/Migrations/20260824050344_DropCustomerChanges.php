<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class DropCustomerChanges extends BaseMigration
{
    public function up(): void
    {
        $this->table('customer_changes')->drop()->save();
    }

    public function down(): void
    {
        $this->table('customer_changes', [
            'id' => false,
            'primary_key' => ['id'],
        ])
            ->addColumn('id', 'uuid')
            ->addColumn('import_id', 'uuid', [
                'null' => false,
            ])
            ->addColumn('square_customer_id', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('action', 'enum', [
                'values' => ['create', 'update', 'delete'],
                'null' => false,
            ])
            ->addColumn('diff', 'text', [
                'null' => true,
                'comment' => 'JSON: {"field": ["old", "new"], ...}',
            ])
            ->addColumn('event_id', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Square webhook event_id, for idempotency; null for full-import changes',
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
            ])
            ->addForeignKey('import_id', 'imports', 'id', [
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->addIndex(['import_id'])
            ->addIndex(['square_customer_id'])
            ->addIndex(['event_id'], ['unique' => true])
            ->create();
    }
}
