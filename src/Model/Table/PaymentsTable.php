<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Payments Model
 *
 * Local mirror of Square payment data, kept in sync by
 * PaymentSyncService. Like Customers and Bookings, this table has no
 * direct relationship to the Square API - it's populated exclusively via
 * sync, and is fully wiped and reloaded on every run rather than diffed.
 *
 * @property \App\Model\Table\CustomersTable&\Cake\ORM\Association\BelongsTo $Customers
 *
 * @method \App\Model\Entity\Payment newEmptyEntity()
 * @method \App\Model\Entity\Payment newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Payment> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Payment get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Payment patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Payment> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Payment|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Payment saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class PaymentsTable extends Table
{
    /**
     * Square payment status values, mapped to their display labels.
     *
     * Single source of truth for the set of valid statuses - used for
     * validation as well as for building the status filter on the
     * payments index page.
     */
    public const STATUSES = [
        'approved' => 'Approved',
        'pending' => 'Pending',
        'completed' => 'Completed',
        'canceled' => 'Canceled',
        'failed' => 'Failed',
    ];

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('payments');
        $this->setPrimaryKey('id');

        // Joined for display purposes only (e.g. showing a customer's name
        // on the payments list) - not a real DB foreign key, since the
        // customers table is fully wiped and reloaded on every sync and
        // couldn't sustain one.
        $this->belongsTo('Customers', [
            'foreignKey' => 'square_customer_id',
            'bindingKey' => 'square_customer_id',
        ]);

        // Auto-populates created_at on insert and updated_at on every
        // save - neither is set explicitly by PaymentSyncService.
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('square_payment_id')
            ->maxLength('square_payment_id', 255)
            ->requirePresence('square_payment_id', 'create')
            ->notEmptyString('square_payment_id');

        $validator
            ->scalar('status')
            ->inList('status', array_keys(self::STATUSES))
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        $validator
            ->integer('amount')
            ->requirePresence('amount', 'create')
            ->notEmptyString('amount');

        $validator
            ->integer('total_amount')
            ->allowEmptyString('total_amount');

        $validator
            ->scalar('source_type')
            ->maxLength('source_type', 50)
            ->allowEmptyString('source_type');

        $validator
            ->scalar('square_customer_id')
            ->maxLength('square_customer_id', 255)
            ->allowEmptyString('square_customer_id');

        $validator
            ->scalar('order_id')
            ->maxLength('order_id', 255)
            ->allowEmptyString('order_id');

        $validator
            ->scalar('receipt_number')
            ->maxLength('receipt_number', 50)
            ->allowEmptyString('receipt_number');

        $validator
            ->scalar('receipt_url')
            ->maxLength('receipt_url', 1024)
            ->allowEmptyString('receipt_url');

        $validator
            ->scalar('note')
            ->allowEmptyString('note');

        $validator
            ->dateTime('square_created_at')
            ->allowEmptyDateTime('square_created_at');

        $validator
            ->dateTime('square_updated_at')
            ->allowEmptyDateTime('square_updated_at');

        return $validator;
    }

    /**
     * Finds payments for a given Square customer ID, most recent first.
     *
     * @param string $squareCustomerId
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findBySquareCustomerId(string $squareCustomerId)
    {
        return $this->find()
            ->where(['square_customer_id' => $squareCustomerId])
            ->orderByDesc('square_created_at');
    }
}
