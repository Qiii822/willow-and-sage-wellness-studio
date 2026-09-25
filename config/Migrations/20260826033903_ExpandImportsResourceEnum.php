<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ExpandImportsResourceEnum extends BaseMigration
{
    public function up(): void
    {
        $this->table('imports')
            ->changeColumn('resource', 'enum', [
                'values' => ['customers', 'bookings', 'payments'],
                'null' => false,
                'default' => 'customers',
                'comment' => 'Which resource this Import row is syncing - lets the shared imports log support more than one synced resource.',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('imports')
            ->changeColumn('resource', 'enum', [
                'values' => ['customers', 'bookings'],
                'null' => false,
                'default' => 'customers',
                'comment' => 'Which resource this Import row is syncing - lets the shared imports log support more than one synced resource.',
            ])
            ->update();
    }
}
