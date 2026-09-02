<?php
declare(strict_types=1);

namespace App\View\Helper;

use App\Service\ImageUploadService;
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
     * One field's text as real paragraphs, split on blank lines.
     *
     * Several paragraphs of prose want to be separate <p> elements rather
     * than one block held apart by <br><br>: the spacing then comes from the
     * stylesheet, and a screen reader announces them as the separate
     * paragraphs they are.
     *
     * The text is escaped first, so only the markup this method adds is ever
     * markup. Single line breaks inside a paragraph are kept as <br>.
     *
     * @param string $key
     * @return string Empty when the field is blank, so the caller can leave
     *  the block out rather than print an empty paragraph.
     */
    public function paragraphs(string $key): string
    {
        $text = trim($this->raw($key));

        if ($text === '') {
            return '';
        }

        // Any run of blank lines separates paragraphs, whatever the line
        // endings the browser submitted.
        $chunks = preg_split('/(\R\s*){2,}/', $text) ?: [];

        $html = '';

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);

            if ($chunk !== '') {
                $html .= '<p>' . nl2br(h($chunk), false) . '</p>';
            }
        }

        return $html;
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
        return $this->imageUrl(
            'hero.image',
            ['hero.webp', 'hero.jpg', 'hero.png', 'homepage.webp', 'homepage.jpg'],
        );
    }

    /**
     * The URL of an uploaded picture, or null when there is none to show.
     *
     * A stored filename whose file has gone missing counts as none, so a
     * stale database row cannot leave the page pointing at a broken image.
     *
     * @param string $key A field declared as an image in SiteContentsTable.
     * @param array<int, string> $fallbacks Pictures shipped in webroot/img to
     *  fall back through, in order of preference. The hero has these; the
     *  other blocks simply leave the picture out when none is uploaded.
     * @return string|null
     */
    public function imageUrl(string $key, array $fallbacks = []): ?string
    {
        $uploaded = $this->raw($key);

        if ($uploaded !== '') {
            $uploads = new ImageUploadService();

            if ($uploads->exists($uploaded)) {
                return $this->getView()->Url->image(
                    ImageUploadService::DIRECTORY . '/' . basename($uploaded),
                );
            }
        }

        foreach ($fallbacks as $candidate) {
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
