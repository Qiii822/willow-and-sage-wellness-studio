<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * One current offer.
 *
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property string|null $terms
 * @property string|null $image
 * @property int $position
 * @property bool $visible
 */
class Offer extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'title' => true,
        'description' => true,
        'terms' => true,
        'position' => true,
        'visible' => true,
        /*
         * Set only by the controller once the upload service has accepted a
         * file and named it. Marking it fillable would let a submitted form
         * point the page at any file it liked.
         */
        'image' => false,
    ];
}
