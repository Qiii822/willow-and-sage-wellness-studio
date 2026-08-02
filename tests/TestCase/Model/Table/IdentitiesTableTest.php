<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\IdentitiesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\IdentitiesTable Test Case
 */
class IdentitiesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\IdentitiesTable
     */
    protected $Identities;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Identities',
        'app.Sessions',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Identities') ? [] : ['className' => IdentitiesTable::class];
        $this->Identities = $this->getTableLocator()->get('Identities', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Identities);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\IdentitiesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
