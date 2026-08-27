<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\ORM\TableRegistry;
use Cake\View\Helper;

/**
 * Reads the home page's editable copy in templates.
 *
 * Loads every field once per request and keeps it, so a page using twenty
 * pieces of copy still makes one query.
 *
 * Everything the studio types is treated as plain text and escaped on the
 * way out: the editor is a text box, not an HTML editor, so a stray angle
 * bracket in someone's copy can never become markup.
 */
class SiteContentHelper extends Helper
{
    /**
     * @var array<string, string>|null
     */
    protected ?array $values = null;

    /**
     * One field's text, escaped and ready to print.
     *
     * @param string $key A key declared in SiteContentsTable::FIELDS.
     * @return string
     */
    public function get(string $key): string
    {
        return h($this->raw($key));
    }

    /**
     * One field's text with its line breaks turned into <br>.
     *
     * The text is escaped first, so only the breaks this method adds are
     * ever markup.
     *
     * @param string $key
     * @return string
     */
    public function lines(string $key): string
    {
        return nl2br(h($this->raw($key)), false);
    }

    /**
     * The unescaped value, for places that are not HTML output - a URL in an
     * attribute the caller escapes itself, or a comparison.
     *
     * @param string $key
     * @return string
     */
    public function raw(string $key): string
    {
        $this->values ??= TableRegistry::getTableLocator()
            ->get('SiteContents')
            ->values();

        return $this->values[$key] ?? '';
    }
}
