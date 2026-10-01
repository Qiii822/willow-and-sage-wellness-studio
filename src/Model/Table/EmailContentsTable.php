<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Throwable;

/**
 * EmailContents Model
 *
 * The wording of the rebooking reminder, editable from the settings page.
 * Only the words are editable - the layout, colours and booking link stay in
 * the template, so nothing typed here can break how the email looks.
 *
 * Text may contain {name} and {days}, filled in per customer when the email
 * is sent.
 */
class EmailContentsTable extends Table
{
    /** Placeholders the reminder text may use, and what each becomes. */
    public const PLACEHOLDERS = [
        '{name}' => "The customer's name",
        '{days}' => 'Days since their last appointment',
    ];

    /**
     * Every editable piece of the reminder, in the order it reads.
     *
     * Each blank on the form falls back to its default, so clearing a field
     * restores the original wording rather than sending a gap.
     */
    public const FIELDS = [
        'rebooking.subject' => [
            'label' => 'Subject line',
            'type' => 'text',
            'default' => 'We’d love to see you again',
        ],
        'rebooking.heading' => [
            'label' => 'Heading',
            'type' => 'text',
            'default' => 'We’d love to see you again!',
        ],
        'rebooking.greeting' => [
            'label' => 'Greeting',
            'type' => 'text',
            'default' => 'Hi {name},',
        ],
        'rebooking.intro' => [
            'label' => 'Opening line',
            'type' => 'textarea',
            'default' => 'It’s been {days} days since your last appointment with Willow and Sage.',
        ],
        'rebooking.body' => [
            'label' => 'Message',
            'type' => 'textarea',
            'default' => 'We hope you’ve been well. We’d love to welcome you back for another relaxing appointment.',
        ],
        'rebooking.cta_heading' => [
            'label' => 'Booking box heading',
            'type' => 'text',
            'default' => 'Ready to book again?',
        ],
        'rebooking.cta_text' => [
            'label' => 'Booking box text',
            'type' => 'textarea',
            'default' => 'Choose a time that works for you using our online booking page.',
        ],
        'rebooking.button' => [
            'label' => 'Button text',
            'type' => 'text',
            'default' => 'Book an Appointment',
        ],
        'rebooking.closing' => [
            'label' => 'Closing line',
            'type' => 'textarea',
            'default' => 'We look forward to seeing you again soon.',
        ],
        'rebooking.signoff' => [
            'label' => 'Sign-off',
            'type' => 'textarea',
            'default' => "Warm regards,\nWillow and Sage Wellness Studio",
        ],
        'rebooking.footer' => [
            'label' => 'Small print',
            'type' => 'textarea',
            'default' => 'You’re receiving this email because you previously booked with Willow and Sage Wellness Studio.',
        ],
    ];

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('email_contents');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);
    }

    /**
     * Every field's current text: the default, unless something was saved.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        $values = array_map(fn(array $field): string => $field['default'], self::FIELDS);

        try {
            $rows = $this->find()->select(['content_key', 'value'])->disableHydration()->all();
        } catch (Throwable $e) {
            // Not migrated yet - the email still sends with its defaults.
            return $values;
        }

        foreach ($rows as $row) {
            if (isset($values[$row['content_key']]) && trim((string)$row['value']) !== '') {
                $values[$row['content_key']] = (string)$row['value'];
            }
        }

        return $values;
    }

    /**
     * Saves submitted wording, ignoring anything not declared in FIELDS.
     *
     * Keys arrive with dots turned to underscores by the browser form
     * (rebooking_subject), so both spellings are accepted.
     *
     * @param array<string, mixed> $submitted
     * @return void
     */
    public function saveValues(array $submitted): void
    {
        foreach (array_keys(self::FIELDS) as $key) {
            $formKey = str_replace('.', '_', $key);
            $value = $submitted[$key] ?? $submitted[$formKey] ?? null;

            if ($value === null) {
                continue;
            }

            $row = $this->find()->where(['content_key' => $key])->first() ?? $this->newEmptyEntity();
            $row->content_key = $key;
            // Same line endings whichever browser it came from.
            $row->value = str_replace("\r\n", "\n", trim((string)$value));

            $this->save($row);
        }
    }

    /**
     * Back to the original wording.
     *
     * @return void
     */
    public function resetToDefaults(): void
    {
        $this->deleteAll(['1=1']);
    }

    /**
     * The reminder's wording for one customer, placeholders filled in.
     *
     * @param string $name
     * @param int|string $days
     * @return array<string, string> Keyed without the "rebooking." prefix.
     */
    public function rebookingFor(string $name, int|string $days): array
    {
        $fill = ['{name}' => $name, '{days}' => (string)$days];
        $content = [];

        foreach ($this->values() as $key => $value) {
            if (str_starts_with($key, 'rebooking.')) {
                $content[substr($key, strlen('rebooking.'))] = strtr($value, $fill);
            }
        }

        return $content;
    }
}
