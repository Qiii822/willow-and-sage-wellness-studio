<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateUsers extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('users', ['id' => false, 'primary_key' => 'id']);
        $table->addColumn('id', 'uuid')
            ->addColumn('email', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('first_name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('surname', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('password', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('role', 'string', [
                'limit' => 50,
                'null' => false,
                'default' => 'user',
            ])
            ->addColumn('active', 'boolean', [
                'default' => true,
                'null' => false,
            ])
            ->addColumn('created_at', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['email'], ['unique' => true])
            ->create();
    }
}
