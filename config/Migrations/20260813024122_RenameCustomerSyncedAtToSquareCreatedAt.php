<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class RenameCustomerSyncedAtToSquareCreatedAt extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('customers');

        $table->renameColumn('synced_at', 'square_created_at')
            ->update();
    }
    
}
