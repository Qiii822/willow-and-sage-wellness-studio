<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Lets an import row record a treatment-menu sync.
 *
 * Every sync writes one `imports` row naming the resource it covered, and
 * the column is an enum, so a new synced resource cannot be recorded until
 * it is listed here.
 */
class AddServicesToImportsResourceEnum extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->execute(
            'ALTER TABLE imports MODIFY resource '
            . "ENUM('customers','bookings','payments','services') NOT NULL",
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        // Rows naming the removed value would be invalid under the narrower
        // enum, so they go first.
        $this->execute("DELETE FROM imports WHERE resource = 'services'");

        $this->execute(
            'ALTER TABLE imports MODIFY resource '
            . "ENUM('customers','bookings','payments') NOT NULL",
        );
    }
}
