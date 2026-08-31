<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Prices and a discount badge for an offer.
 *
 * Money in whole cents, as elsewhere: storing a decimal invites rounding
 * drift, and Square reports amounts this way too.
 *
 * The percentage is stored rather than always derived because an offer can
 * be a flat "20% off facials" with no single before and after price to
 * subtract. When both prices are set the badge is worked out from them, so
 * the two can never end up contradicting each other on the card.
 */
class AddPricingToOffers extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('offers')
            ->addColumn('price_before_cents', 'integer', [
                'null' => true,
                'after' => 'terms',
            ])
            ->addColumn('price_after_cents', 'integer', [
                'null' => true,
                'after' => 'price_before_cents',
            ])
            ->addColumn('discount_percent', 'integer', [
                'null' => true,
                'after' => 'price_after_cents',
            ])
            ->update();
    }
}
