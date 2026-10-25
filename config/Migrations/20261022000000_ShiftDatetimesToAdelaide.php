<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Moves the stored datetimes from Melbourne's clock to Adelaide's.
 *
 * The ORM writes a datetime as wall-clock time in PHP's default zone, and
 * that was Australia/Melbourne while the studio - and Square's location -
 * is in Adelaide. config/bootstrap.php now says Adelaide, so the same rows
 * would otherwise be read back half an hour late. Adelaide is always thirty
 * minutes behind Melbourne: both change for daylight saving on the same
 * nights, half an hour apart, so only a time inside that half hour around
 * 2-3am on those Sundays can come out wrong.
 *
 * Listed by hand rather than found in the schema, because some datetime
 * columns were never on Melbourne's clock and must not move:
 *
 *  - booking_drafts.start_at holds the UTC instant Square gave us, and
 *    BookingDraft reads it back as UTC on purpose.
 *  - sessions.created / modified are filled by MySQL's CURRENT_TIMESTAMP,
 *    which runs in the connection's UTC.
 *  - cake_migrations and cake_seeds belong to the migrations plugin.
 */
class ShiftDatetimesToAdelaide extends BaseMigration
{
    /**
     * @var array<string, array<int, string>>
     */
    protected const COLUMNS = [
        'bookings' => ['start_at', 'square_updated_at', 'square_created_at', 'created_at', 'updated_at'],
        'booking_access_codes' => ['expires_at', 'used_at', 'created_at', 'modified'],
        'booking_charges' => ['created_at', 'updated_at'],
        'booking_drafts' => ['created_at', 'modified'],
        'contact_change_reverts' => ['expires_at', 'used_at', 'created_at'],
        'customers' => ['square_updated_at', 'square_created_at', 'deleted_at', 'created_at', 'updated_at'],
        'email_contents' => ['created_at', 'updated_at'],
        'identities' => ['created_at', 'expires_at', 'refreshed_at', 'revoked_at'],
        'imports' => ['started_at', 'finished_at'],
        'mail_settings' => ['verified_at', 'created_at', 'modified'],
        'offers' => ['created_at', 'modified'],
        'payments' => ['square_created_at', 'square_updated_at', 'created_at', 'updated_at'],
        'services' => ['created_at', 'modified'],
        'service_categories' => ['created_at', 'modified'],
        'site_contents' => ['created_at', 'modified'],
        'site_sections' => ['created_at', 'modified'],
        'users' => ['created_at', 'modified', 'reset_token_expires'],
        'verification_codes' => ['expires_at', 'consumed_at', 'created_at'],
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->shift('-');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->shift('+');
    }

    /**
     * Moves every listed column thirty minutes in one direction.
     *
     * @param string $sign '-' towards Adelaide, '+' back to Melbourne.
     * @return void
     */
    protected function shift(string $sign): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $set = array_map(
                // A NULL stays NULL, so "not used yet" columns are safe here.
                fn(string $column): string => "`{$column}` = `{$column}` {$sign} INTERVAL 30 MINUTE",
                $columns,
            );

            $this->execute("UPDATE `{$table}` SET " . implode(', ', $set));
        }
    }
}
