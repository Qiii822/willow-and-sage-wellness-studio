<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Throwable;

/**
 * The studio's treatment menu.
 *
 * Rows are entered by hand for now. `square_catalog_id` is left on the table
 * so a future catalogue sync can match a Square item to the row it already
 * created, instead of duplicating the menu.
 */
class ServicesTable extends Table
{
    /**
     * @param array<string, mixed> $config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('services');
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
            ->scalar('category')
            ->maxLength('category', 100)
            ->notEmptyString('category', 'Choose or type a category.');

        $validator
            ->scalar('name')
            ->maxLength('name', 150)
            ->notEmptyString('name', 'A treatment name is required.');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->integer('price_cents')
            ->greaterThanOrEqual('price_cents', 0, 'A price cannot be negative.')
            ->allowEmptyString('price_cents');

        $validator
            ->integer('duration_minutes')
            ->greaterThan('duration_minutes', 0, 'A duration must be more than zero.')
            ->allowEmptyString('duration_minutes');

        return $validator;
    }

    /**
     * Treatments the public site should show, grouped under their category.
     *
     * Categories come out in the order of their first treatment, so moving a
     * treatment to the top of the list moves its whole category with it -
     * which is how someone arranging a menu expects it to behave.
     *
     * @return array<string, array<int, \App\Model\Entity\Service>>
     */
    public function menu(): array
    {
        try {
            $rows = $this->find()
                ->where(['visible' => true])
                ->orderBy(['position' => 'ASC', 'name' => 'ASC'])
                ->all();
        } catch (Throwable $e) {
            // Table not migrated yet; the page shows nothing rather than failing.
            return [];
        }

        $menu = [];

        foreach ($rows as $service) {
            $menu[$service->category][] = $service;
        }

        return $menu;
    }

    /**
     * Every treatment, shown or hidden, for the management screen.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function forManagement(): SelectQuery
    {
        return $this->find()->orderBy(['position' => 'ASC', 'name' => 'ASC']);
    }

    /**
     * The categories already in use, for the editor's suggestions.
     *
     * @return array<int, string>
     */
    public function categories(): array
    {
        try {
            return $this->find()
                ->select(['category'])
                ->distinct(['category'])
                ->orderBy(['category' => 'ASC'])
                ->all()
                ->extract('category')
                ->toList();
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Puts a treatment at the end of the menu.
     *
     * @return int
     */
    public function nextPosition(): int
    {
        $highest = $this->find()
            ->select(['position'])
            ->orderBy(['position' => 'DESC'])
            ->first();

        return $highest ? (int)$highest->position + 1 : 0;
    }

    /**
     * Applies a submitted order.
     *
     * @param array<int, string> $ids Treatment ids, first to last.
     * @return void
     */
    public function reorder(array $ids): void
    {
        $position = 0;

        foreach ($ids as $id) {
            $this->updateAll(['position' => $position++], ['id' => $id]);
        }
    }
}
