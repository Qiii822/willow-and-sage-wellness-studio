<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Identities Model
 *
 * @property \App\Model\Table\SessionsTable&\Cake\ORM\Association\HasMany $Sessions
 *
 * @method \App\Model\Entity\Identity newEmptyEntity()
 * @method \App\Model\Entity\Identity newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Identity> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Identity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Identity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Identity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Identity> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Identity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Identity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Identity>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Identity>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Identity>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Identity> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Identity>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Identity>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Identity>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Identity> deleteManyOrFail(iterable $entities, array $options = [])
 */
class IdentitiesTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('identities');
        $this->setDisplayField('merchant_id');
        $this->setPrimaryKey('id');

        $this->hasMany('Sessions', [
            'foreignKey' => 'identity_id',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('merchant_id')
            ->maxLength('merchant_id', 255)
            ->requirePresence('merchant_id', 'create')
            ->notEmptyString('merchant_id');

        $validator
            ->scalar('tokens')
            ->allowEmptyString('tokens');

        $validator
            ->dateTime('created_at')
            ->requirePresence('created_at', 'create')
            ->notEmptyDateTime('created_at');

        $validator
            ->dateTime('expires_at')
            ->requirePresence('expires_at', 'create')
            ->notEmptyDateTime('expires_at');

        $validator
            ->dateTime('refreshed_at')
            ->allowEmptyDateTime('refreshed_at');

        $validator
            ->boolean('revoked')
            ->notEmptyString('revoked');

        $validator
            ->dateTime('revoked_at')
            ->allowEmptyDateTime('revoked_at');

        return $validator;
    }
}
