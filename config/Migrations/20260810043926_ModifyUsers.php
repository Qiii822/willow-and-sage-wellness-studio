<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ModifyUsers extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('users');
 
        $table->renameColumn('role', 'roles')->update();
 
        $table = $this->table('users');
        $table->changeColumn('roles', 'enum', [
            'values' => ['user', 'admin', 'superadmin'],
            'null' => false,
            'default' => 'user',
        ])->update();
    }
 
    public function down(): void
    {
        $table = $this->table('users');
        $table->changeColumn('roles', 'string', [
            'limit' => 50,
            'null' => false,
            'default' => 'user',
        ])->update();
 
        $table = $this->table('users');
        $table->renameColumn('roles', 'role')->update();
    }
}
