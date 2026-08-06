<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class DropSessionsTable extends BaseMigration
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
        $this->table('sessions')->drop()->save();
    }
 
    public function down(): void
    {
        $sessionsTable = $this->table('sessions', ['id' => false, 'primary_key' => 'id']);
        $sessionsTable->addColumn('id', 'uuid');
        $sessionsTable->addColumn('identity_id', 'uuid', [
            'null' => false,
        ]);
        $sessionsTable->addForeignKey('identity_id', 'identities', 'id', [
            'update' => 'CASCADE',
            'delete' => 'CASCADE',
        ]);
        $sessionsTable->addColumn('created_at', 'datetime', [
            'null' => false,
        ]);
        $sessionsTable->addColumn('expires_at', 'datetime', [
            'null' => true,
        ]);
        $sessionsTable->addColumn('revoked', 'boolean', [
            'null' => false,
            'default' => false,
        ]);
        $sessionsTable->addColumn('revoked_at', 'datetime', [
            'null' => true,
        ]);
        $sessionsTable->create();
    }


}
