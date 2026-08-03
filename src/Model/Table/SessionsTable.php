<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\I18n\FrozenTime;

/**
 * Sessions Model
 *
 * @property \App\Model\Table\IdentitiesTable&\Cake\ORM\Association\BelongsTo $Identities
 *
 * @method \App\Model\Entity\Session newEmptyEntity()
 * @method \App\Model\Entity\Session newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Session> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Session get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Session findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Session patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Session> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Session|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Session saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Session>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Session>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Session>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Session> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Session>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Session>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Session>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Session> deleteManyOrFail(iterable $entities, array $options = [])
 */
class SessionsTable extends Table
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

        $this->setTable('sessions');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Identities', [
            'foreignKey' => 'identity_id',
            'joinType' => 'INNER',
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
            ->uuid('identity_id')
            ->notEmptyString('identity_id');

        $validator
            ->dateTime('created_at')
            ->requirePresence('created_at', 'create')
            ->notEmptyDateTime('created_at');

        $validator
            ->dateTime('expires_at')
            ->allowEmptyDateTime('expires_at');

        $validator
            ->boolean('revoked')
            ->notEmptyString('revoked');

        $validator
            ->dateTime('revoked_at')
            ->allowEmptyDateTime('revoked_at');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['identity_id'], 'Identities'), ['errorField' => 'identity_id']);

        return $rules;
    }

    public function findValidById(string $sessionId)
    {
        return $this->find()
            ->where([
                'Sessions.id' => $sessionId,
                'Sessions.revoked' => false,
            ])
            ->andWhere(['Sessions.expires_at >' => FrozenTime::now()])
            ->contain(['Identities'])
            ->first();
    }
}
