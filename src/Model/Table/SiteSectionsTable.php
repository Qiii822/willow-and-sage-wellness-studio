<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Throwable;

/**
 * Which blocks the home page shows, in what order, and how each is laid out.
 *
 * SECTIONS declares the blocks that exist and the arrangements each one
 * supports. Stored rows only record the studio's choices, so an empty table
 * means the page appears exactly as designed - which is also what a fresh
 * install and a database that has not been migrated yet will show.
 *
 * Arrangements are a fixed list rather than free-form styling on purpose:
 * every combination here is one that has been designed, so the page cannot
 * be made to look broken from the editor.
 */
class SiteSectionsTable extends Table
{
    /**
     * @var array<string, array{label: string, description: string, element: string, variants: array<string, string>}>
     */
    public const SECTIONS = [
        'hero' => [
            'label' => 'Welcome banner',
            'description' => 'The large photo, heading and booking button.',
            'element' => 'home/hero',
            'variants' => [
                'left' => 'Text on the left',
                'centre' => 'Text centred',
            ],
        ],
        'services_intro' => [
            'label' => 'About the studio',
            'description' => 'The heading and paragraph introducing the services.',
            'element' => 'home/services_intro',
            'variants' => [
                'left' => 'Left aligned',
                'centre' => 'Centred',
            ],
        ],
        'services_grid' => [
            'label' => 'Service cards',
            'description' => 'The three treatments, side by side or stacked.',
            'element' => 'home/services_grid',
            'variants' => [
                'grid' => 'Three across',
                'rows' => 'Stacked rows',
            ],
        ],
        'services_menu' => [
            'label' => 'Treatment menu overview',
            'description' => 'The categories from the services page, with a link to the full menu. '
                . 'Hidden automatically while no treatments have been entered.',
            'element' => 'home/services_menu',
            'variants' => [
                'grid' => 'Cards across',
                'list' => 'Simple list',
            ],
        ],
    ];

    /**
     * @param array<string, mixed> $config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('site_sections');
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
     * The declared default for one section: shown, in declaration order,
     * using its first arrangement.
     *
     * @param string $key
     * @param int $position
     * @return array{key: string, label: string, description: string, element: string, variants: array<string, string>, position: int, visible: bool, variant: string}
     */
    protected function defaultsFor(string $key, int $position): array
    {
        $section = self::SECTIONS[$key];

        return $section + [
            'key' => $key,
            'position' => $position,
            'visible' => true,
            'variant' => (string)array_key_first($section['variants']),
        ];
    }

    /**
     * Every section with its current settings, in the order the page shows
     * them.
     *
     * Sections with no stored row keep their declared defaults, and stored
     * rows for sections no longer declared are ignored, so adding or
     * removing a block in code needs no data migration.
     *
     * @return array<int, array<string, mixed>>
     */
    public function layout(): array
    {
        $sections = [];
        $position = 0;

        foreach (array_keys(self::SECTIONS) as $key) {
            $sections[$key] = $this->defaultsFor($key, $position++);
        }

        try {
            $rows = $this->find()->disableHydration()->all();
        } catch (Throwable $e) {
            // Not migrated yet; the page still renders as designed.
            return array_values($sections);
        }

        foreach ($rows as $row) {
            $key = $row['section_key'];

            if (!isset($sections[$key])) {
                continue;
            }

            $sections[$key]['position'] = (int)$row['position'];
            $sections[$key]['visible'] = (bool)$row['visible'];

            // An arrangement that no longer exists falls back to the default.
            if (isset(self::SECTIONS[$key]['variants'][(string)$row['variant']])) {
                $sections[$key]['variant'] = (string)$row['variant'];
            }
        }

        $sections = array_values($sections);

        usort(
            $sections,
            fn(array $a, array $b): int => $a['position'] <=> $b['position'],
        );

        return $sections;
    }

    /**
     * Only the sections the page should actually render.
     *
     * @return array<int, array<string, mixed>>
     */
    public function visibleLayout(): array
    {
        return array_values(array_filter(
            $this->layout(),
            fn(array $section): bool => $section['visible'],
        ));
    }

    /**
     * Stores a submitted layout.
     *
     * Order comes from the sequence the keys arrive in rather than from a
     * submitted number, so the saved order always matches what was on
     * screen even if the rows were rearranged client-side.
     *
     * @param array<int, string> $order Section keys, first to last.
     * @param array<string, mixed> $visible Keys present are shown.
     * @param array<string, string> $variants Key to chosen arrangement.
     * @return void
     */
    public function saveLayout(array $order, array $visible, array $variants): void
    {
        $position = 0;

        foreach ($order as $key) {
            if (!isset(self::SECTIONS[$key])) {
                continue;
            }

            $row = $this->find()->where(['section_key' => $key])->first()
                ?? $this->newEmptyEntity();

            $chosen = (string)($variants[$key] ?? '');

            $row->section_key = $key;
            $row->position = $position++;
            $row->visible = !empty($visible[$key]);
            $row->variant = isset(self::SECTIONS[$key]['variants'][$chosen])
                ? $chosen
                : (string)array_key_first(self::SECTIONS[$key]['variants']);

            $this->save($row);
        }
    }

    /**
     * Puts every section back to the arrangement declared in code.
     *
     * @return void
     */
    public function resetToDefaults(): void
    {
        $this->deleteAll(['1=1']);
    }
}
