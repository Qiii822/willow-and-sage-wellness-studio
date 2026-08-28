<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Layout of the public home page: which blocks appear, in what order, and
 * in which arrangement.
 *
 * The blocks themselves are declared in code - this only records the
 * choices made about them, so a missing row simply means "as designed".
 */
class CreateSiteSections extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('site_sections', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('section_key', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('position', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('visible', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('variant', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['section_key'], ['unique' => true])
            ->create();
    }
}
