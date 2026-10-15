<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * BookingCharges Model
 *
 * What the studio knows about a booking that Square does not keep: the
 * menu price it was charged against, whether it has been marked as owing,
 * and whether the studio was the one who cancelled it.
 *
 * Unlike Bookings and Payments this is not a mirror of Square and is never
 * touched by a sync - rows are keyed on the Square booking id so they
 * line up with the booking again after every reimport.
 *
 * @method \App\Model\Entity\BookingCharge newEmptyEntity()
 * @method \App\Model\Entity\BookingCharge newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\BookingCharge saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class BookingChargesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('booking_charges');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('square_booking_id')
            ->maxLength('square_booking_id', 255)
            ->requirePresence('square_booking_id', 'create')
            ->notEmptyString('square_booking_id');

        $validator
            ->integer('price_cents')
            ->allowEmptyString('price_cents');

        $validator
            ->boolean('unpaid');

        $validator
            ->boolean('cancelled_by_staff');

        return $validator;
    }

    /**
     * The charge row for a booking, made if there is none yet.
     *
     * @param string $squareBookingId
     * @return \App\Model\Entity\BookingCharge
     */
    public function forBooking(string $squareBookingId): \App\Model\Entity\BookingCharge
    {
        return $this->find()->where(['square_booking_id' => $squareBookingId])->first()
            ?? $this->newEntity(['square_booking_id' => $squareBookingId]);
    }

    /**
     * Charge rows for many bookings at once, keyed by Square booking id.
     *
     * @param array<string> $squareBookingIds
     * @return array<string, \App\Model\Entity\BookingCharge>
     */
    public function forBookings(array $squareBookingIds): array
    {
        if ($squareBookingIds === []) {
            return [];
        }

        $charges = [];

        foreach ($this->find()->where(['square_booking_id IN' => $squareBookingIds])->all() as $charge) {
            $charges[$charge->square_booking_id] = $charge;
        }

        return $charges;
    }
}
