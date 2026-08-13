<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateCustomers extends BaseMigration
{
    public function up(): void
    {
        $this->table('customers', [
            'id' => false,
            'primary_key' => ['id'],
        ])
            ->addColumn('id', 'uuid')
            ->addColumn('square_customer_id', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('given_name', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('family_name', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('email_address', 'string', [
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('phone_number', 'string', [
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('square_version', 'integer', [
                'null' => false,
            ])
            ->addColumn('square_updated_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('synced_at', 'datetime', [
                'null' => false,
            ])
            ->addColumn('deleted_at', 'datetime', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
            ])
            ->addColumn('updated_at', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['square_customer_id'], ['unique' => true])
            ->create();
    }
 
    public function down(): void
    {
        $this->table('customers')->drop()->save();
    }
}
