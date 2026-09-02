<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Hash;
use Throwable;

/**
 * Editable copy for the public home page.
 *
 * FIELDS is the single source of truth: it drives the editor form, the
 * whitelist of keys a submission may write, and the text the site shows
 * before anyone has edited anything. Adding a new editable piece of copy
 * means adding one entry here and reading it in the template - no migration,
 * no form markup.
 */
class SiteContentsTable extends Table
{
    /** Single-line text input. */
    public const TYPE_TEXT = 'text';

    /** Multi-line box; newlines survive to the page as line breaks. */
    public const TYPE_TEXTAREA = 'textarea';

    /**
     * An uploaded picture. The stored value is a filename inside
     * webroot/img/uploads, written by the upload service rather than by
     * saveValues(), which only handles typed text.
     */
    public const TYPE_IMAGE = 'image';

    /**
     * @var array<string, array{group: string, label: string, type: string, default: string, help?: string, validate?: string}>
     */
    public const FIELDS = [
        'site.title' => [
            'group' => 'General',
            'label' => 'Browser tab title',
            'type' => self::TYPE_TEXT,
            'default' => 'Willow and Sage Wellness Studio',
        ],
        'site.brand' => [
            'group' => 'General',
            'label' => 'Studio name in the header',
            'type' => self::TYPE_TEXT,
            'default' => 'Willow and Sage Wellness Studio',
        ],
        'booking.url' => [
            'group' => 'General',
            'label' => 'Booking link',
            'type' => self::TYPE_TEXT,
            'default' => 'https://book.squareup.com/appointments/r2a8ubnahl2pug'
                . '/location/LPSB9KGV9MYAJ/services'
                . '?buttonTextColor=ffffff&color=4d4d4d&locale=en-AU&referrer=so',
            'help' => 'Where the Book buttons send people. Must start with https://',
            'validate' => 'url',
        ],

        'hero.image' => [
            'group' => 'Hero',
            'label' => 'Background photo',
            'type' => self::TYPE_IMAGE,
            'default' => '',
            'help' => 'JPEG, PNG or WebP, up to 5 MB. Landscape works best; '
                . 'around 2000px wide keeps it sharp on large screens. '
                . 'With none set, the photo shipped with the site is used.',
        ],
        'hero.eyebrow' => [
            'group' => 'Hero',
            'label' => 'Small line above the heading',
            'type' => self::TYPE_TEXT,
            'default' => 'Wellness studio · Ingle Farm, Adelaide',
        ],
        'hero.title' => [
            'group' => 'Hero',
            'label' => 'Main heading',
            'type' => self::TYPE_TEXTAREA,
            'default' => "Slow down.\nUnwind.\nFeel nurtured.",
            'help' => 'Each line appears on its own line on the page.',
        ],
        'hero.lead' => [
            'group' => 'Hero',
            'label' => 'Sentence under the heading',
            'type' => self::TYPE_TEXTAREA,
            'default' => 'Relaxation massage, facials and natural nail care in a '
                . 'calming home-based studio.',
        ],
        'hero.cta' => [
            'group' => 'Hero',
            'label' => 'Button text',
            'type' => self::TYPE_TEXT,
            'default' => 'Book a treatment',
        ],

        'about.heading' => [
            'group' => 'About Wendy',
            'label' => 'Section heading',
            'type' => self::TYPE_TEXT,
            'default' => 'About Wendy and Willow & Sage',
        ],
        'about.image' => [
            'group' => 'About Wendy',
            'label' => 'Photo of Wendy',
            'type' => self::TYPE_IMAGE,
            'default' => '',
            'help' => 'JPEG, PNG or WebP, up to 5MB. The section leaves the '
                . 'picture out until one is uploaded.',
        ],
        'about.body' => [
            'group' => 'About Wendy',
            'label' => 'Introduction',
            'type' => self::TYPE_TEXTAREA,
            'default' => "Hi, I'm Wendy, the beauty therapist behind Willow & Sage "
                . 'Wellness Studio - a peaceful home-based studio in Ingle Farm, '
                . "Adelaide. Visits are by appointment only.\n\n"
                . 'I created Willow & Sage as a calm, welcoming space where you can '
                . 'slow down, feel cared for and enjoy unhurried, personalised '
                . 'treatments. I offer relaxation massage, organic facials, '
                . 'manicures, pedicures, natural nail care, waxing and tinting, with '
                . "every appointment tailored to your comfort and needs.\n\n"
                . 'I also provide gentle mobile wellness experiences for community '
                . 'groups, retirement villages, aged-care settings and special '
                . "events.\n\n"
                . "Whether you're visiting for relaxation, confidence or a little "
                . "time for yourself, you'll always receive warm, down-to-earth care.",
            'help' => 'Leave a blank line between paragraphs.',
        ],

        'location.heading' => [
            'group' => 'Location and hours',
            'label' => 'Section heading',
            'type' => self::TYPE_TEXT,
            'default' => 'Location and Hours',
        ],
        'location.address' => [
            'group' => 'Location and hours',
            'label' => 'Address',
            'type' => self::TYPE_TEXTAREA,
            'default' => "Willow and Sage Wellness Studio\n"
                . "13 Eudunda Ave\n"
                . 'Ingle Farm, South Australia 5098',
            'help' => 'One line per line of the address.',
        ],
        'location.map_query' => [
            'group' => 'Location and hours',
            'label' => 'What the map should point at',
            'type' => self::TYPE_TEXT,
            'default' => '',
            'help' => 'Leave blank to use the address above. Fill this in only '
                . 'if the map lands on the wrong spot - a plain street address '
                . 'usually works better than the studio name.',
        ],
        'location.hours' => [
            'group' => 'Location and hours',
            'label' => 'Opening hours',
            'type' => self::TYPE_TEXTAREA,
            'default' => "Monday: 9:00 am - 7:00 pm\n"
                . "Tuesday: 10:00 am - 5:00 pm\n"
                . "Wednesday: Closed\n"
                . "Thursday: 9:00 am - 9:00 pm\n"
                . "Friday: Closed\n"
                . "Saturday: 9:00 am - 5:00 pm\n"
                . 'Sunday: Closed',
            'help' => 'One day per line, written as "Monday: 9:00 am - 7:00 pm". '
                . 'The part before the colon is the day.',
        ],

        'safety.heading' => [
            'group' => 'Comfort and hygiene',
            'label' => 'Section heading',
            'type' => self::TYPE_TEXT,
            'default' => 'Your comfort, hygiene and safety',
        ],
        'safety.image' => [
            'group' => 'Comfort and hygiene',
            'label' => 'Photo of the studio',
            'type' => self::TYPE_IMAGE,
            'default' => '',
            'help' => 'JPEG, PNG or WebP, up to 5MB. The section leaves the '
                . 'picture out until one is uploaded.',
        ],
        'safety.body' => [
            'group' => 'Comfort and hygiene',
            'label' => 'Wording',
            'type' => self::TYPE_TEXTAREA,
            'default' => 'At Willow & Sage Wellness Studio, your comfort, hygiene and '
                . 'safety are always a priority. Treatments are provided using '
                . 'professional hygiene practices, clean tools, fresh towels where '
                . "required and individual comfort checks.\n\n"
                . 'Mobile wellness services are gentle and relaxation-based, with '
                . "treatments adjusted to each client's comfort, mobility, skin "
                . "condition and suitability.\n\n"
                . 'For your wellbeing, a treatment may be modified, shortened or '
                . 'declined if there are health, hygiene, skin, safety or '
                . "infection-control concerns.\n\n"
                . 'Full hygiene and safety procedures are available upon request for '
                . 'aged-care centres, retirement villages and community organisations.',
            'help' => 'Leave a blank line between paragraphs.',
        ],

        'offers.heading' => [
            'group' => 'Offers',
            'label' => 'Section heading',
            'type' => self::TYPE_TEXT,
            'default' => 'Current offers',
        ],
        'offers.intro' => [
            'group' => 'Offers',
            'label' => 'Section introduction',
            'type' => self::TYPE_TEXTAREA,
            'default' => '',
            'help' => 'Optional. A line above the offers, if they need one.',
        ],

        'services.heading' => [
            'group' => 'Services',
            'label' => 'Section heading',
            'type' => self::TYPE_TEXT,
            'default' => 'Service menu',
        ],
        'services.menu_heading' => [
            'group' => 'Services',
            'label' => 'Heading on the services page',
            'type' => self::TYPE_TEXT,
            'default' => 'What we offer',
        ],
        'services.intro' => [
            'group' => 'Services',
            'label' => 'Section introduction',
            'type' => self::TYPE_TEXTAREA,
            'default' => 'Willow & Sage Wellness Studio is a calming home-based '
                . 'wellness space in Ingle Farm, Adelaide, offering relaxation '
                . 'massage, facials, and natural nail care treatments designed to '
                . 'help you slow down, unwind, and feel nurtured.',
        ],

        'service.1.title' => [
            'group' => 'Services',
            'label' => 'Service 1 - name',
            'type' => self::TYPE_TEXT,
            'default' => 'Relaxation massage',
        ],
        'service.1.body' => [
            'group' => 'Services',
            'label' => 'Service 1 - description',
            'type' => self::TYPE_TEXTAREA,
            'default' => 'Unhurried, full-body massage to release held tension and '
                . 'quiet a busy head.',
        ],
        'service.2.title' => [
            'group' => 'Services',
            'label' => 'Service 2 - name',
            'type' => self::TYPE_TEXT,
            'default' => 'Facials',
        ],
        'service.2.body' => [
            'group' => 'Services',
            'label' => 'Service 2 - description',
            'type' => self::TYPE_TEXTAREA,
            'default' => 'Gentle, skin-first facials chosen to suit how your skin is '
                . 'behaving on the day.',
        ],
        'service.3.title' => [
            'group' => 'Services',
            'label' => 'Service 3 - name',
            'type' => self::TYPE_TEXT,
            'default' => 'Natural nail care',
        ],
        'service.3.body' => [
            'group' => 'Services',
            'label' => 'Service 3 - description',
            'type' => self::TYPE_TEXTAREA,
            'default' => 'Manicures and pedicures using natural products, finished '
                . 'without the rush.',
        ],

        'footer.name' => [
            'group' => 'Footer',
            'label' => 'Studio name',
            'type' => self::TYPE_TEXT,
            'default' => 'Willow and Sage Wellness Studio',
        ],
        'footer.location' => [
            'group' => 'Footer',
            'label' => 'Location',
            'type' => self::TYPE_TEXT,
            'default' => 'Ingle Farm, Adelaide',
        ],
        'footer.hours' => [
            'group' => 'Footer',
            'label' => 'Opening hours',
            'type' => self::TYPE_TEXT,
            'default' => 'Open until 7:00 pm',
        ],
        'footer.phone' => [
            'group' => 'Footer',
            'label' => 'Phone',
            'type' => self::TYPE_TEXT,
            'default' => '+61 458 033 899',
        ],
        'footer.email' => [
            'group' => 'Footer',
            'label' => 'Email',
            'type' => self::TYPE_TEXT,
            'default' => 'Willowandsagewellness26@gmail.com',
        ],
    ];

    /**
     * The form field an image is uploaded through, e.g. "about.image"
     * becomes "about_image".
     *
     * Dots would be read as nested arrays by the form helper, so an image
     * field cannot carry its own key. Both the editor that renders the input
     * and the controller that reads the upload go through here, because a
     * disagreement between them would silently drop every upload.
     *
     * @param string $key
     * @return string
     */
    public static function imageInputName(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    /**
     * The Flash key an image field's upload error is reported under, so the
     * message renders beside that field rather than at the top of the page.
     *
     * @param string $key
     * @return string
     */
    public static function imageFlashKey(string $key): string
    {
        // "hero.image" -> "heroImage", matching the existing hero key.
        return lcfirst(str_replace(' ', '', ucwords(str_replace('.', ' ', $key))));
    }

    /**
     * @param array<string, mixed> $config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('site_contents');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'modified' => 'always',
                ],
            ],
        ]);
    }

    /**
     * Every field's current text, keyed by field name.
     *
     * Starts from the declared defaults and lays saved rows over the top, so
     * the result always covers every key even on an empty table.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        $values = array_map(
            fn(array $field): string => $field['default'],
            self::FIELDS,
        );

        try {
            $rows = $this->find()
                ->select(['content_key', 'value'])
                ->disableHydration()
                ->all();
        } catch (Throwable $e) {
            // Table not migrated yet: the page still renders on defaults.
            return $values;
        }

        foreach ($rows as $row) {
            // Ignore rows for keys no longer declared, and blanks, so the
            // page never shows an empty heading where copy should be.
            if (isset($values[$row['content_key']]) && trim((string)$row['value']) !== '') {
                $values[$row['content_key']] = (string)$row['value'];
            }
        }

        return $values;
    }

    /**
     * Writes submitted copy, ignoring anything not declared in FIELDS.
     *
     * A field whose value fails its declared `validate` rule is skipped
     * whatever was already saved for it stays in place - and reported back
     * in the returned array so the caller can tell the admin what went wrong
     *
     * @param array<string, mixed> $submitted
     * @return array<string, string> Field key to error message, for any
     *  field that was rejected. Empty when everything submitted was valid.
     */
    public function saveValues(array $submitted): array
    {
        /*
         * Field names contain dots, which the form helper renders as nested
         * inputs - `hero.title` arrives as $data['hero']['title']. Flattening
         * turns that structure back into the dotted keys used here, so the
         * key in FIELDS stays the one true name for a field.
         */
        $submitted = Hash::flatten($submitted);

        $errors = [];

        foreach (self::FIELDS as $key => $field) {
            // Pictures arrive as uploads, not as text in the form data.
            if ($field['type'] === self::TYPE_IMAGE) {
                continue;
            }

            if (!array_key_exists($key, $submitted)) {
                continue;
            }

            // Normalise line endings so stored text does not vary by browser.
            $value = str_replace("\r\n", "\n", trim((string)$submitted[$key]));

            $validationError = $this->validationErrorFor($field, $value);

            if ($validationError !== null) {
                $errors[$key] = $field['label'] . ': ' . $validationError;

                continue;
            }

            $row = $this->find()->where(['content_key' => $key])->first()
                ?? $this->newEmptyEntity();

            $row->content_key = $key;
            $row->value = $value;

            $this->save($row);
        }

        return $errors;
    }

    /**
     * Checks one field's submitted value against its declared `validate`
     * rule, if it has one.
     *
     * A blank value is always allowed regardless of rule - values() already
     * falls back to the field's default when the stored value is blank, so
     * clearing a field is a valid way to reset it rather than something to
     * reject here.
     *
     * @param array{group: string, label: string, type: string, default: string, help?: string, validate?: string} $field
     * @param string $value The trimmed, submitted value.
     * @return string|null An error message, or null when the value is fine.
     */
    protected function validationErrorFor(array $field, string $value): ?string
    {
        if ($value === '' || !isset($field['validate'])) {
            return null;
        }

        if ($field['validate'] === 'url') {
            $isHttps = str_starts_with(strtolower($value), 'https://');
            $isWellFormed = filter_var($value, FILTER_VALIDATE_URL) !== false;

            if (!$isHttps || !$isWellFormed) {
                return 'Must be a full web address starting with https://';
            }
        }

        return null;
    }

    /**
     * Stores one field's value directly, bypassing the text whitelist.
     *
     * Used for image fields, whose value is a filename produced by the
     * upload service rather than something the browser submitted.
     *
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function setValue(string $key, string $value): bool
    {
        if (!isset(self::FIELDS[$key])) {
            return false;
        }

        $row = $this->find()->where(['content_key' => $key])->first()
            ?? $this->newEmptyEntity();

        $row->content_key = $key;
        $row->value = $value;

        return (bool)$this->save($row);
    }

    /**
     * Restores every field to the text declared in FIELDS.
     *
     * Uploaded files are left on disk: the row pointing at them is what is
     * removed, and deleting them is the caller's business since it needs to
     * know which files were in use.
     *
     * @return void
     */
    public function resetToDefaults(): void
    {
        $this->deleteAll(['1=1']);
    }
}
