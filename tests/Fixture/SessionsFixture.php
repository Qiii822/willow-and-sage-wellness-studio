<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * SessionsFixture
 */
class SessionsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 'bd5b8325-e3f1-4e07-a014-13d08d7a6c4a',
                'identity_id' => '26768177-78e4-42f9-9610-cdf389b7f8aa',
                'created_at' => '2026-08-02 02:45:12',
                'expires_at' => '2026-08-02 02:45:12',
                'revoked' => 1,
                'revoked_at' => '2026-08-02 02:45:12',
            ],
        ];
        parent::init();
    }
}
