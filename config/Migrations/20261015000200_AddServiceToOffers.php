<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * The treatment an offer is about.
 *
 * Square cannot answer this. A discount in Square's catalogue says how much
 * it takes off and nothing about what it takes it off - that lives on a
 * separate pricing rule the studio has to create, and most accounts have
 * none. So the connection is made here instead, chosen on the offer.
 *
 * With it, an offer card can send the customer straight into booking that
 * treatment, and the treatment can be priced with the discount already
 * taken off instead of at full price beside an offer advertising it.
 *
 * Nullable: an offer may be a general one - "10% off everything in June" -
 * with no single treatment behind it.
 */
class AddServiceToOffers extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('offers')
            ->addColumn('service_id', 'char', [
                'limit' => 36,
                'null' => true,
                'after' => 'title',
                'comment' => 'The treatment this offer is for; null for a general offer.',
            ])
            /*
             * Indexed, not a foreign key. The services table is emptied and
             * reloaded on every catalogue sync, so a constraint here would
             * either block the sync or quietly delete the offers alongside
             * it. A dropped treatment leaves the column pointing at nothing,
             * which the offer reads as "no treatment" - the same as never
             * having chosen one.
             */
            ->addIndex(['service_id'])
            ->update();
    }
}
