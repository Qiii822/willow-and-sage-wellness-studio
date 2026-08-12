<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * CustomerChanges Model
 *
 * @property \App\Model\Table\ImportsTable&\Cake\ORM\Association\BelongsTo $Imports
 *
 * @method \App\Model\Entity\CustomerChange newEmptyEntity()
 * @method \App\Model\Entity\CustomerChange newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\CustomerChange get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\CustomerChange patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\CustomerChange|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\CustomerChange saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class CustomerChangesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('customer_changes');
        $this->setPrimaryKey('id');

        $this->belongsTo('Imports', [
            'foreignKey' => 'import_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('import_id')
            ->requirePresence('import_id', 'create')
            ->notEmptyString('import_id');

        $validator
            ->scalar('square_customer_id')
            ->maxLength('square_customer_id', 255)
            ->requirePresence('square_customer_id', 'create')
            ->notEmptyString('square_customer_id');

        $validator
            ->scalar('action')
            ->inList('action', ['create', 'update', 'delete'])
            ->requirePresence('action', 'create')
            ->notEmptyString('action');

        $validator
            ->scalar('diff')
            ->allowEmptyString('diff');

        $validator
            ->scalar('event_id')
            ->maxLength('event_id', 255)
            ->allowEmptyString('event_id');

        $validator
            ->dateTime('created_at')
            ->requirePresence('created_at', 'create')
            ->notEmptyDateTime('created_at');

        return $validator;
    }
}
