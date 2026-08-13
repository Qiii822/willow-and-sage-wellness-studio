<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateImports extends BaseMigration
{
    public function up(): void
    {
        $this->table('imports', [
            'id' => false,
            'primary_key' => ['id'],
        ])
            ->addColumn('id', 'uuid')
            ->addColumn('identity_id', 'uuid', [
                'null' => false,
            ])
            ->addColumn('source', 'enum', [
                'values' => ['oauth_callback', 'cron', 'manual', 'webhook'],
                'null' => false,
            ])
            ->addColumn('status', 'enum', [
                'values' => ['running', 'completed', 'failed'],
                'null' => false,
                'default' => 'running',
            ])
            ->addColumn('started_at', 'datetime', [
                'null' => false,
            ])
            ->addColumn('finished_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('error_message', 'text', [
                'null' => true,
            ])
            ->addForeignKey('identity_id', 'identities', 'id', [
                'update' => 'CASCADE',
                'delete' => 'CASCADE',
            ])
            ->addIndex(['status'])
            ->create();
    }
 
    public function down(): void
    {
        $this->table('imports')->drop()->save();
    }
}
