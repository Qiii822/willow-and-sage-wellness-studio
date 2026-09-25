<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * The categories a treatment can be filed under.
 *
 * A category is a string on each service, which is enough to group the
 * public page but means a category cannot exist before something uses it -
 * there is nowhere to put one. This table is that place, so the studio can
 * set up the headings first and pick from them afterwards.
 *
 * It does not own the grouping. `services.category` is still what the page
 * reads; this is the list of names offered when choosing one, kept in step
 * by the sync as Square's categories arrive.
 */
class CreateServiceCategories extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('service_categories', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            // Two categories with the same name would be the same category.
            ->addIndex(['name'], ['unique' => true])
            ->create();
    }
}
