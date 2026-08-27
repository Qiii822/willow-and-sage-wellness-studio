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
     * The hero backdrop's URL, or null to leave the gradient showing.
     *
     * Prefers a photo uploaded through the editor. With none set - or when
     * its file has gone missing - it falls back to the picture shipped in
     * webroot/img, so the page never loses its backdrop because of a stale
     * database row.
     *
     * @return string|null
     */
    public function heroImageUrl(): ?string
    {
        $uploaded = $this->raw('hero.image');

        if ($uploaded !== '') {
            $uploads = new \App\Service\ImageUploadService();

            if ($uploads->exists($uploaded)) {
                return $this->getView()->Url->image(
                    \App\Service\ImageUploadService::DIRECTORY . '/' . basename($uploaded)
                );
            }
        }

        foreach (['hero.webp', 'hero.jpg', 'hero.png', 'homepage.webp', 'homepage.jpg'] as $candidate) {
            if (file_exists(WWW_ROOT . 'img' . DS . $candidate)) {
                return $this->getView()->Url->image($candidate);
            }
        }

        return null;
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
