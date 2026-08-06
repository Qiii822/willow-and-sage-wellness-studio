<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;
use Authentication\PasswordHasher\DefaultPasswordHasher;

class User extends Entity
{
    protected array $_accessible = [
        'email' => true,
        'first_name' => true,
        'surname' => true,
        'password' => true,
        'active' => true,
        'created_at' => true,
        'modified' => true,
    ];

    protected array $_hidden = [
        'password',
    ];

    protected function _setPassword(string $password): ?string
    {
        if (mb_strlen($password) > 0) {
            return (new DefaultPasswordHasher())->hash($password);
        }
        return null;
    }

}
