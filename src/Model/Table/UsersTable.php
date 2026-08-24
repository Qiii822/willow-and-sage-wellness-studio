<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UsersTable extends Table
{
    /**
     * Field length limits, shared by every screen that creates or edits an
     * account so the same account data rules apply wherever it comes from.
     * The forms mirror these as `maxlength` attributes.
     */
    public const NAME_MAX = 20;
    public const EMAIL_MAX = 50;
    public const PASSWORD_MIN = 8;

    /*
     * Bcrypt only reads the first 72 bytes of a password, so a cap below that
     * costs nothing in storage terms. 64 keeps a margin under that limit
     * while still admitting passphrases and password-manager output.
     */
    public const PASSWORD_MAX = 64;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('email');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'modified' => 'always',
                ],
            ],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->email('email')
            ->maxLength('email', self::EMAIL_MAX, 'Email address cannot be more than ' . self::EMAIL_MAX . ' characters')
            ->requirePresence('email', 'create')
            ->notEmptyString('email');

        $validator
            ->scalar('first_name')
            ->maxLength('first_name', self::NAME_MAX, 'First name cannot be more than ' . self::NAME_MAX . ' characters')
            ->requirePresence('first_name', 'create')
            ->notEmptyString('first_name');

        $validator
            ->scalar('surname')
            ->maxLength('surname', self::NAME_MAX, 'Surname cannot be more than ' . self::NAME_MAX . ' characters')
            ->requirePresence('surname', 'create')
            ->notEmptyString('surname');

        $validator = $this->applyPasswordRules($validator, true);

        $validator
            ->boolean('active')
            ->notEmptyString('active');

        $validator
            ->scalar('roles')
            ->inList('roles', ['user', 'admin', 'superadmin'], 'Role must be either user, admin, or superadmin.')
            ->notEmptyString('roles');

        $validator
            ->dateTime('created_at')
            ->notEmptyDateTime('created_at');

        $validator
            ->dateTime('modified')
            ->notEmptyDateTime('modified');

        return $validator;
    }

    public function validationChangePassword(Validator $validator): Validator
    {
        return $this->applyPasswordRules($validator, true);
    }

    protected function applyPasswordRules(Validator $validator, bool $requirePresence = true): Validator
    {
        $validator
            ->scalar('password');

        $validator
            ->scalar('password_confirm');

        if ($requirePresence) {
            $validator
                ->requirePresence('password', 'create')
                ->requirePresence('password_confirm', 'create');
        }

        $validator
            ->notEmptyString('password', 'Password is required')
            ->maxLength('password', self::PASSWORD_MAX, 'Password cannot be more than ' . self::PASSWORD_MAX . ' characters')
            ->minLength('password', self::PASSWORD_MIN, 'Password must be at least ' . self::PASSWORD_MIN . ' characters')
            ->add('password', 'containsUpper', [
                'rule' => function ($value) {
                    return (bool)preg_match('/[A-Z]/', $value);
                },
                'message' => 'Password must contain at least one uppercase letter',
            ])
            ->add('password', 'containsLower', [
                'rule' => function ($value) {
                    return (bool)preg_match('/[a-z]/', $value);
                },
                'message' => 'Password must contain at least one lowercase letter',
            ])
            ->add('password', 'containsNumber', [
                'rule' => function ($value) {
                    return (bool)preg_match('/[0-9]/', $value);
                },
                'message' => 'Password must contain at least one number',
            ]);

        // Validate the confirmation matches the password
        $validator
            ->notEmptyString('password_confirm', 'Please confirm your password')
            ->add('password_confirm', 'compareWith', [
                'rule' => ['compareWith', 'password'],
                'message' => 'Passwords do not match',
            ]);

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['email']), ['errorField' => 'email']);

        return $rules;
    }
}
