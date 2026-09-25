<?php
declare(strict_types=1);

namespace App\Model\Entity;

use App\Utility\PhoneNumber;
use Cake\ORM\Entity;
use Authentication\PasswordHasher\DefaultPasswordHasher;

class User extends Entity
{
    protected array $_accessible = [
        'email' => true,
        'first_name' => true,
        'surname' => true,
        'phone' => true,
        'password' => true,
        'password_confirm' => true,
        'active' => true,
        'roles' => true,
        'reset_token' => true,
        'reset_token_expires' => true,
        'created_at' => true,
        'modified' => true,
    ];

    protected array $_hidden = [
        'password',
    ];

    /**
     * Numbers are stored in one canonical spelling, so that the unique index
     * and the Square matching compare the number itself rather than however
     * the person happened to punctuate it.
     *
     * An unrecognisable value is kept as-is for the validator to reject; only
     * a number that actually parses gets rewritten.
     */
    protected function _setPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        return PhoneNumber::normalise($phone) ?? $phone;
    }

    protected function _setPassword(string $password): ?string
    {
        if (mb_strlen($password) > 0) {
            return (new DefaultPasswordHasher())->hash($password);
        }
        return null;
    }

}
