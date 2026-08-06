<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateSessions extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/guides/writing-migrations/migration-methods.html#the-change-method
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('sessions', [
            'id' => false,
            'primary_key' => ['id'],
        ])
            ->addColumn('id', 'char', [
                'limit' => 40,
                'null' => false,
                'encoding' => 'ascii',
                'collation' => 'ascii_bin',
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('data', 'blob', [
                'null' => true,
                'default' => null,
            ])
            ->addColumn('expires', 'integer', [
                'limit' => 10,
                'signed' => false,
                'null' => true,
                'default' => null,
            ])
            ->create();
    }
 
    public function down(): void
    {
        $this->table('sessions')->drop()->save();
    }
}
