<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Current offers, shown as a block on the home page.
 *
 * A table rather than a fixed set of content fields because offers come and
 * go: a winter special replaces a first-visit discount, two run at once in
 * December, none run in February. Storing them as rows means the studio adds
 * and removes them, instead of needing the page rebuilt each season.
 */
class CreateOffers extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('offers', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('title', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('description', 'text', ['null' => true])
            /*
             * The small print - "New clients only", "Until 30 June". Kept
             * apart from the description so it can be styled as the aside it
             * is, rather than buried in the sales copy.
             */
            ->addColumn('terms', 'string', ['limit' => 255, 'null' => true])
            /*
             * Filename only. The upload service owns the directory and
             * generates the name, so nothing here comes from the browser.
             */
            ->addColumn('image', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('position', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('visible', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['position'])
            ->create();
    }
}
