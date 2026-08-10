<?php
declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * Users seed.
 */
class UsersSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/migrations/5/guides/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                'id' => '09153c1d-5b6e-4e87-8b4c-4bc2788a085c',
                'email' => 'wendy@example.com',
                'first_name' => 'Wendy',
                'surname' => 'S',
                'password' => '$2y$12$aPC1zgtQ72XhWRc3eXoWJuer0PmrmIfTgD9Ut3cVOkUGQPY6SSoBO',
                'role' => 'superadmin',
                'active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
        ];

        $table = $this->table('users');
        $table->insert($data)->save();
    }
}
