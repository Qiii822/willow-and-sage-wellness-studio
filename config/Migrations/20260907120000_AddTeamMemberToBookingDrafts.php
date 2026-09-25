<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Which therapist the customer asked for.
 *
 * Null means they did not mind, which is a real answer rather than a
 * missing one: Square is then free to offer whoever is available, and the
 * customer sees more times than picking a person would give them.
 */
class AddTeamMemberToBookingDrafts extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('booking_drafts')
            ->addColumn('team_member_id', 'string', [
                'limit' => 64,
                'null' => true,
                'default' => null,
                'after' => 'segments',
            ])
            ->update();
    }
}
