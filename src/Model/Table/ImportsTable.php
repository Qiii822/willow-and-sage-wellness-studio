<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Imports Model
 *
 * @property \App\Model\Table\IdentitiesTable&\Cake\ORM\Association\BelongsTo $Identities
 *
 * @method \App\Model\Entity\Import newEmptyEntity()
 * @method \App\Model\Entity\Import newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Import get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Import patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Import|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Import saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class ImportsTable extends Table
{
    /**
     * How long a `running` import is trusted before being treated as
     * stale (e.g. the process crashed without ever setting status to
     * failed).
     */
    protected const STALE_AFTER_MINUTES = 30;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('imports');
        $this->setPrimaryKey('id');

        $this->belongsTo('Identities', [
            'foreignKey' => 'identity_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('identity_id')
            ->requirePresence('identity_id', 'create')
            ->notEmptyString('identity_id');

        $validator
            ->scalar('resource')
            ->inList('resource', ['customers', 'bookings', 'payments', 'services', 'offers'])
            ->requirePresence('resource', 'create')
            ->notEmptyString('resource');

        $validator
            ->scalar('source')
            ->inList('source', ['oauth_callback', 'cron', 'manual', 'webhook'])
            ->requirePresence('source', 'create')
            ->notEmptyString('source');

        $validator
            ->scalar('status')
            ->inList('status', ['running', 'completed', 'failed'])
            ->notEmptyString('status');

        $validator
            ->dateTime('started_at')
            ->requirePresence('started_at', 'create')
            ->notEmptyDateTime('started_at');

        $validator
            ->dateTime('finished_at')
            ->allowEmptyDateTime('finished_at');

        $validator
            ->scalar('error_message')
            ->allowEmptyString('error_message');

        return $validator;
    }

    /**
     * Whether an import is currently running (and not stale). Used to
     * stop two syncs writing to the same table at once.
     *
     * @param string|null $resource Limit the check to one resource
     *  (e.g. 'customers', 'bookings'). Omit to check across all resources -
     *  used where any in-flight sync matters, e.g. blocking disconnect.
     * @return bool
     */
    public function hasRunningImport(?string $resource = null): bool
    {
        $query = $this->find()
            ->where(['status' => 'running'])
            ->andWhere(['started_at >' => DateTime::now()->subMinutes(self::STALE_AFTER_MINUTES)]);

        if ($resource !== null) {
            $query = $query->andWhere(['resource' => $resource]);
        }

        return $query->count() > 0;
    }

    /**
     * Returns the most recently completed import.
     *
     * @param string|null $resource Limit to one resource (e.g. 'customers',
     *  'bookings'). Omit to get the most recent completed import overall.
     * @return \App\Model\Entity\Import|null
     */
    public function getLastCompleted(?string $resource = null): ?\App\Model\Entity\Import
    {
        $query = $this->find()->where(['status' => 'completed']);

        if ($resource !== null) {
            $query = $query->andWhere(['resource' => $resource]);
        }

        return $query->orderByDesc('finished_at')->first();
    }
}
