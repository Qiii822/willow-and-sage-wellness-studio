<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * IdentitiesFixture
 */
class IdentitiesFixture extends TestFixture
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
                'id' => 'ca3d26f3-ec41-4c6b-a967-e9111547729b',
                'merchant_id' => 'Lorem ipsum dolor sit amet',
                'tokens' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'created_at' => '2026-08-02 02:45:12',
                'expires_at' => '2026-08-02 02:45:12',
                'refreshed_at' => '2026-08-02 02:45:12',
                'revoked' => 1,
                'revoked_at' => '2026-08-02 02:45:12',
            ],
        ];
        parent::init();
    }
}
