<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * The studio's treatment menu, as shown on the public site.
 *
 * Entered by hand for now. `square_catalog_id` is here from the start so a
 * later catalogue sync can match an existing row and update it, rather than
 * inserting a duplicate alongside the hand-entered one; until then it stays
 * null and nothing depends on it.
 */
class CreateServices extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('services', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('category', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('description', 'text', ['null' => true])
            /*
             * Money as whole cents. Storing a decimal invites rounding drift,
             * and Square itself reports amounts this way.
             */
            ->addColumn('price_cents', 'integer', ['null' => true])
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'AUD'])
            ->addColumn('duration_minutes', 'integer', ['null' => true])
            ->addColumn('position', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('visible', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('square_catalog_id', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['category'])
            ->addIndex(['position'])
            ->addIndex(['square_catalog_id'], ['unique' => true])
            ->create();
    }
}
