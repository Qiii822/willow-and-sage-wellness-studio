<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Throwable;

/**
 * The offers currently running.
 */
class OffersTable extends Table
{
    /**
     * @param array<string, mixed> $config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('offers');
        $this->setPrimaryKey('id');
        $this->setDisplayField('title');

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
            ->scalar('title')
            ->maxLength('title', 150)
            ->notEmptyString('title', 'An offer needs a name.');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('terms')
            ->maxLength('terms', 255)
            ->allowEmptyString('terms');

        $validator
            ->integer('price_before_cents')
            ->greaterThanOrEqual('price_before_cents', 0, 'A price cannot be negative.')
            ->allowEmptyString('price_before_cents');

        $validator
            ->integer('price_after_cents')
            ->greaterThanOrEqual('price_after_cents', 0, 'A price cannot be negative.')
            ->allowEmptyString('price_after_cents');

        $validator
            ->integer('discount_percent')
            ->range('discount_percent', [1, 99], 'A discount must be between 1 and 99 percent.')
            ->allowEmptyString('discount_percent');

        /*
         * A "now" price at or above the "was" price is not an offer, and
         * would print a strike-through that reads as a price rise. Caught
         * here rather than quietly hidden at render time, so whoever typed it
         * finds out.
         */
        $validator->add('price_after_cents', 'lowerThanBefore', [
            'rule' => function ($value, $context) {
                $before = $context['data']['price_before_cents'] ?? null;

                if ($before === null || $before === '' || $value === null || $value === '') {
                    return true;
                }

                return (int)$value < (int)$before;
            },
            'message' => 'The offer price has to be lower than the usual price.',
        ]);

        return $validator;
    }

    /**
     * The offers the public page should show, in the studio's order.
     *
     * @return array<int, \App\Model\Entity\Offer>
     */
    public function current(): array
    {
        try {
            return $this->find()
                ->where(['visible' => true])
                ->orderBy(['position' => 'ASC', 'title' => 'ASC'])
                ->all()
                ->toList();
        } catch (Throwable $e) {
            // Table not migrated yet; the block simply does not appear.
            return [];
        }
    }

    /**
     * Every offer, shown or hidden, for the management screen.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function forManagement(): SelectQuery
    {
        return $this->find()->orderBy(['position' => 'ASC', 'title' => 'ASC']);
    }

    /**
     * Puts a new offer at the end of the list.
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
     * @param array<int, string> $ids Offer ids, first to last.
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
