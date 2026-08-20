<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Utility\Security;
use Cake\Datasource\EntityInterface;

/**
 * Identities Model
 *
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

    private function decryptedTokens(EntityInterface $identity): array
    {
        $key = env('TOKEN_ENCRYPTION_KEY');
        $decrypted = Security::decrypt(base64_decode($identity->tokens), $key);

        return json_decode($decrypted, true) ?? [];
    }

    public function encryptTokens(array $tokens): string
    {
        $key = env('TOKEN_ENCRYPTION_KEY');
        $encrypted = Security::encrypt(json_encode($tokens), $key);

        return base64_encode($encrypted);
    }

    public function getDecryptedAccessToken(EntityInterface $identity): ?string
    {
        $tokens = $this->decryptedTokens($identity);

        return $tokens['access_token'] ?? null;
    }

    public function getDecryptedRefreshToken(EntityInterface $identity): ?string
    {
        $tokens = $this->decryptedTokens($identity);

        return $tokens['refresh_token'] ?? null;
    }

    /**
     * Returns the app's currently active (non-revoked) Square Identity, or
     * null if Square has never been connected or has been disconnected.
     *
     */
    public function getActiveConnection(): ?\Cake\Datasource\EntityInterface
    {
        return $this->find()
            ->where(['revoked' => false])
            ->orderByDesc('created_at')
            ->first();
    }
}
