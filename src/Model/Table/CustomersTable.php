<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Customers Model
 *
 * Local mirror of Square customer data, kept in sync by
 * CustomerSyncService. This table has no direct relationship to the
 * Square API - it's populated exclusively via sync.
 *
 * @method \App\Model\Entity\Customer newEmptyEntity()
 * @method \App\Model\Entity\Customer newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Customer> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Customer get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Customer patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Customer> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Customer|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Customer saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class CustomersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('customers');
        $this->setDisplayField('given_name');
        $this->setPrimaryKey('id');

        // Auto-populates created_at on insert and updated_at on every
        // save - neither is set explicitly by CustomerSyncService.
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
            ->scalar('square_customer_id')
            ->maxLength('square_customer_id', 255)
            ->requirePresence('square_customer_id', 'create')
            ->notEmptyString('square_customer_id');

        $validator
            ->scalar('given_name')
            ->maxLength('given_name', 255)
            ->allowEmptyString('given_name');

        $validator
            ->scalar('family_name')
            ->maxLength('family_name', 255)
            ->allowEmptyString('family_name');

        $validator
            ->scalar('email_address')
            ->maxLength('email_address', 255)
            ->allowEmptyString('email_address');

        $validator
            ->scalar('phone_number')
            ->maxLength('phone_number', 50)
            ->allowEmptyString('phone_number');

        $validator
            ->integer('square_version')
            ->requirePresence('square_version', 'create')
            ->notEmptyString('square_version');

        $validator
            ->dateTime('square_updated_at')
            ->allowEmptyDateTime('square_updated_at');

        $validator
            ->dateTime('square_created_at')
            ->requirePresence('square_created_at', 'create')
            ->notEmptyDateTime('square_created_at');

        $validator
            ->dateTime('deleted_at')
            ->allowEmptyDateTime('deleted_at');

        return $validator;
    }

    /**
     * Finds the local Customer record for a given Square customer ID,
     * including soft-deleted ones (sync needs to see deleted rows too, so
     * it can "undelete" a customer that reappears in Square).
     *
     * @param string $squareCustomerId
     * @return \App\Model\Entity\Customer|null
     */
    public function findBySquareCustomerId(string $squareCustomerId): ?\App\Model\Entity\Customer
    {
        return $this->find()
            ->where(['square_customer_id' => $squareCustomerId])
            ->first();
    }
}
