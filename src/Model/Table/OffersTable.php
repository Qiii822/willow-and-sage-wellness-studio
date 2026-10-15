<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
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

        /*
         * Left join, because an offer need not be about one treatment, and
         * because the catalogue sync empties the services table - so an
         * offer can briefly point at a treatment that is not there. Either
         * way the offer still has to load.
         */
        $this->belongsTo('Services', [
            'foreignKey' => 'service_id',
            'joinType' => 'LEFT',
        ]);

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

        /*
         * Up to 100 rather than 99: a discount of the whole price is a real
         * thing to run - a free treatment with a course of them - and Square
         * will happily report one. Capping at 99 made a sync fail outright
         * on an offer the studio had every right to set up.
         */
        $validator
            ->integer('discount_percent')
            ->range('discount_percent', [1, 100], 'A discount must be between 1 and 100 percent.')
            ->allowEmptyString('discount_percent');

        $validator
            ->integer('discount_amount_cents')
            ->greaterThanOrEqual('discount_amount_cents', 0, 'A discount cannot be negative.')
            ->allowEmptyString('discount_amount_cents');

        $validator
            ->scalar('square_catalog_id')
            ->maxLength('square_catalog_id', 191)
            ->allowEmptyString('square_catalog_id');

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
     * @param \Cake\ORM\RulesChecker $rules
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        /*
         * One offer per Square discount. The column has a unique index too -
         * this is here so a second one is reported as a validation failure
         * the sync can log, rather than a raw database error.
         *
         * `allowMultipleNulls` because every hand-written offer has no Square
         * id, and they are not duplicates of one another.
         */
        $rules->add(
            $rules->isUnique(
                ['square_catalog_id'],
                ['allowMultipleNulls' => true, 'message' => 'That Square discount already has an offer.'],
            ),
            'uniqueSquareCatalogId',
        );

        return $rules;
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
                ->contain(['Services'])
                ->where(['Offers.visible' => true])
                ->orderBy(['Offers.position' => 'ASC', 'Offers.title' => 'ASC'])
                ->all()
                ->toList();
        } catch (Throwable $e) {
            // Table not migrated yet; the block simply does not appear.
            return [];
        }
    }

    /**
     * The running offers that are about one treatment, keyed by its id.
     *
     * How the menu and the booking page know to price a treatment with its
     * discount already taken off, rather than at full price beside an offer
     * advertising a discount on it.
     *
     * Only one offer per treatment: two discounts on the same thing is not
     * something this site can show, and the first in the studio's own order
     * is the one it means to lead with.
     *
     * @return array<string, \App\Model\Entity\Offer>
     */
    public function currentByServiceId(): array
    {
        $byService = [];

        foreach ($this->current() as $offer) {
            $id = (string)$offer->service_id;

            if ($id !== '' && !isset($byService[$id])) {
                $byService[$id] = $offer;
            }
        }

        return $byService;
    }

    /**
     * Every offer, shown or hidden, for the management screen.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function forManagement(): SelectQuery
    {
        return $this->find()
            ->contain(['Services'])
            ->orderBy(['Offers.position' => 'ASC', 'Offers.title' => 'ASC']);
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
