<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Bookings Model
 *
 * Local mirror of Square booking data, kept in sync by
 * BookingSyncService. Like Customers, this table has no direct
 * relationship to the Square API - it's populated exclusively via sync,
 * and is fully wiped and reloaded on every run rather than diffed.
 *
 * @method \App\Model\Entity\Booking newEmptyEntity()
 * @method \App\Model\Entity\Booking newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Booking> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Booking get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Booking patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Booking> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Booking|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Booking saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class BookingsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('bookings');
        $this->setPrimaryKey('id');

        // Auto-populates created_at on insert and updated_at on every
        // save - neither is set explicitly by BookingSyncService.
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
            ->scalar('square_booking_id')
            ->maxLength('square_booking_id', 255)
            ->requirePresence('square_booking_id', 'create')
            ->notEmptyString('square_booking_id');

        $validator
            ->scalar('status')
            ->inList('status', [
                'pending',
                'cancelled_by_customer',
                'cancelled_by_seller',
                'declined',
                'accepted',
                'no_show',
            ])
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        $validator
            ->scalar('location_id')
            ->maxLength('location_id', 255)
            ->allowEmptyString('location_id');

        $validator
            ->scalar('square_customer_id')
            ->maxLength('square_customer_id', 255)
            ->allowEmptyString('square_customer_id');

        $validator
            ->dateTime('start_at')
            ->allowEmptyDateTime('start_at');

        $validator
            ->scalar('customer_note')
            ->allowEmptyString('customer_note');

        $validator
            ->scalar('seller_note')
            ->allowEmptyString('seller_note');

        $validator
            ->scalar('appointment_segments')
            ->allowEmptyString('appointment_segments');

        $validator
            ->integer('square_version')
            ->requirePresence('square_version', 'create')
            ->notEmptyString('square_version');

        $validator
            ->dateTime('square_updated_at')
            ->allowEmptyDateTime('square_updated_at');

        $validator
            ->dateTime('square_created_at')
            ->allowEmptyDateTime('square_created_at');

        return $validator;
    }

    /**
     * Finds bookings for a given Square customer ID, most recent first.
     *
     * @param string $squareCustomerId
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findBySquareCustomerId(string $squareCustomerId)
    {
        return $this->find()
            ->where(['square_customer_id' => $squareCustomerId])
            ->orderByDesc('start_at');
    }
}
