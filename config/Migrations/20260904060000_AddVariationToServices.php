<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * The Square variation a treatment is booked as.
 *
 * `square_catalog_id` holds the catalogue item, which is what the menu is
 * built from. Bookings are made against the item's *variation* though -
 * that is where Square keeps the price and the duration, and it is what
 * both the availability search and CreateBooking ask for. Without it the
 * menu can be shown but nothing on it can be booked.
 */
class AddVariationToServices extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('services')
            ->addColumn('square_variation_id', 'string', [
                'limit' => 255,
                'null' => true,
                'after' => 'square_catalog_id',
            ])
            ->update();
    }
}
