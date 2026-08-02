<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateIdentitiesAndSessions extends BaseMigration
{
    public function change(): void
    {
        // Create identities table with UUID primary key
        $identitiesTable = $this->table('identities', ['id' => false, 'primary_key' => 'id']);
        $identitiesTable->addColumn('id', 'uuid');
        $identitiesTable->addColumn('merchant_id', 'string', [
            'limit' => 255,
            'null' => false,
        ]);
        $identitiesTable->addColumn('tokens', 'text', [
            'null' => true,
        ]);
        $identitiesTable->addColumn('created_at', 'datetime', [
            'null' => false,
        ]);
        $identitiesTable->addColumn('expires_at', 'datetime', [
            'null' => false,
        ]);
        $identitiesTable->addColumn('refreshed_at', 'datetime', [
            'null' => true,
        ]);
        $identitiesTable->addColumn('revoked', 'boolean', [
            'null' => false,
            'default' => false,
        ]);
        $identitiesTable->addColumn('revoked_at', 'datetime', [
            'null' => true,
        ]);
        $identitiesTable->create();

        // Create sessions table with UUID foreign key
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