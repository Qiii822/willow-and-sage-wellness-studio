<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Ties an offer to the Square discount it came from.
 *
 * Without the id there is nothing to match a discount against on the next
 * sync, so every run would add the same offer again rather than update the
 * one already here. It is nullable because an offer may still be written
 * here by hand: Square's discount carries no description, small print or
 * picture, which is most of what an offer card actually says.
 *
 * `discount_amount_cents` is the other half of a Square discount. Square
 * discounts come two ways - a percentage off, or a fixed amount off - and
 * the existing `discount_percent` can only hold the first. Without this a
 * "$20 off" discount would sync across with no discount to show at all.
 */
class AddSquareFieldsToOffers extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('offers')
            ->addColumn('square_catalog_id', 'string', [
                'limit' => 191,
                'null' => true,
                'after' => 'discount_percent',
                'comment' => 'The Square catalogue discount this offer mirrors; null for one written here by hand.',
            ])
            ->addColumn('discount_amount_cents', 'integer', [
                'null' => true,
                'after' => 'discount_percent',
                'comment' => 'A fixed amount off, in cents, for a Square discount that is not a percentage.',
            ])
            /*
             * Unique rather than a plain index: two offers mirroring one
             * Square discount is the bug this column exists to prevent, so
             * the database refuses it rather than trusting the sync to.
             */
            ->addIndex(['square_catalog_id'], ['unique' => true])
            ->update();
    }
}
