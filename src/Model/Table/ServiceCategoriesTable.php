<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Throwable;

/**
 * The list of category names a treatment can be filed under.
 */
class ServiceCategoriesTable extends Table
{
    /**
     * @param array<string, mixed> $config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('service_categories');
        $this->setPrimaryKey('id');
        $this->setDisplayField('name');

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
     * @param \Cake\Validation\Validator $validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 100)
            ->notEmptyString('name', 'A category needs a name.');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(
            $rules->isUnique(['name'], 'That category already exists.'),
        );

        return $rules;
    }

    /**
     * Every category name, in the order they should be offered.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        try {
            return $this->find()
                ->select(['name'])
                ->orderBy(['name' => 'ASC'])
                ->all()
                ->extract('name')
                ->toList();
        } catch (Throwable $e) {
            // Not migrated yet; the form falls back to what is in use.
            return [];
        }
    }

    /**
     * Makes the list exactly these names, adding and removing as needed.
     *
     * Used by the sync, which mirrors Square rather than merging with it -
     * a category typed in locally that Square does not have is removed,
     * the same way a locally added treatment is.
     *
     * Rows that are already correct are left alone rather than deleted and
     * recreated, so their ids survive and the Remove links on a page
     * loaded moments earlier still point at something.
     *
     * @param array<int, string> $names
     * @return array{added: int, removed: int}
     */
    public function replaceWith(array $names): array
    {
        $wanted = [];

        foreach ($names as $name) {
            $name = trim((string)$name);

            if ($name !== '') {
                // Keyed case-insensitively so "Massage" and "massage" do
                // not both survive as separate headings.
                $wanted[mb_strtolower($name)] = $name;
            }
        }

        $added = 0;
        $removed = 0;

        foreach ($this->find()->all() as $existing) {
            $key = mb_strtolower($existing->name);

            if (isset($wanted[$key])) {
                // Already listed; nothing to do but stop it being re-added.
                unset($wanted[$key]);

                continue;
            }

            if ($this->delete($existing)) {
                $removed++;
            }
        }

        foreach ($wanted as $name) {
            if ($this->save($this->newEntity(['name' => $name]))) {
                $added++;
            }
        }

        return ['added' => $added, 'removed' => $removed];
    }
}
