<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddResourceToImports extends BaseMigration
{
    public function up(): void
    {
        $this->table('imports')
            ->addColumn('resource', 'enum', [
                'values' => ['customers', 'bookings'],
                'null' => false,
                'default' => 'customers',
                'after' => 'identity_id',
                'comment' => 'Which resource this Import row is syncing - lets the shared imports log support more than one synced resource.',
            ])
            ->addIndex(['resource', 'status'])
            ->update();
    }

    public function down(): void
    {
        $this->table('imports')
            ->removeIndex(['resource', 'status'])
            ->removeColumn('resource')
            ->update();
    }
}
