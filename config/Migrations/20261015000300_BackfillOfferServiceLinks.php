<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Points the offers that already existed at their treatment.
 *
 * The sync guesses the treatment from the discount's name, but only when it
 * first creates an offer - so that a studio who corrects the guess is not
 * overruled by the next sync, and so that "no single treatment" stays a
 * choice rather than something re-guessed every run.
 *
 * That leaves the offers synced before the column existed with nothing in
 * it, and no run will ever fill them in. This is that one run.
 *
 * Only an exact, unambiguous name match. A near match here would silently
 * aim an offer at the wrong treatment and send customers to book it, which
 * is worse than leaving the studio to pick.
 */
class BackfillOfferServiceLinks extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->execute(
            'UPDATE offers o '
            . 'JOIN services s ON s.name = o.title '
            . 'SET o.service_id = s.id '
            . 'WHERE o.service_id IS NULL '
            // Nothing is guessed when two treatments share the name: there
            // would be no way to tell which one the offer meant.
            . 'AND (SELECT COUNT(*) FROM services s2 WHERE s2.name = o.title) = 1',
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        /*
         * Nothing. The column is dropped by the migration that added it, and
         * clearing every link here would also throw away the ones the studio
         * chose by hand - which this never set.
         */
    }
}
