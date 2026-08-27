<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Editable copy for the public home page.
 *
 * One row per piece of text, keyed by the name the template asks for. Rows
 * are written only when someone changes something: a key with no row falls
 * back to the default declared in SiteContentsTable, so the page reads
 * correctly on a fresh database and before anyone opens the editor.
 */
class CreateSiteContents extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('site_contents', [
            'id' => false,
            'primary_key' => ['id'],
        ]);

        $table
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('content_key', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('value', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['content_key'], ['unique' => true])
            ->create();
    }
}
