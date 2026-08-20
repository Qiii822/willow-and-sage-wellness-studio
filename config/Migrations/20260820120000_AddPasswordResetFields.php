<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddPasswordResetFields extends BaseMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('reset_token', 'string', [
                'limit' => 64,
                'null' => true,
            ])
            ->addColumn('reset_token_expires', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['reset_token'], ['unique' => true])
            ->update();
    }
}