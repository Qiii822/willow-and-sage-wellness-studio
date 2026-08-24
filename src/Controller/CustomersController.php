<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

class CustomersController extends AppController
{
    public function index(): Response
    {
        $customers = $this->fetchTable('Customers')
            ->find()
            ->orderBy([
                'square_created_at' => 'DESC',
            ])
            ->all();

        $this->set(compact('customers'));

        return $this->render();
    }

    /**
     * View Customer method
     *
     * Displays a single local customer along with their upcoming
     * (start_at in the future) bookings.
     *
     * @param string|null $id Customer id.
     * @return \Cake\Http\Response
     * @throws \Cake\Datasource\Exception\RecordNotFoundException
     */
    public function view(?string $id = null): Response
    {
        $customer = $this->fetchTable('Customers')->get($id);

        $upcomingBookings = $this->fetchTable('Bookings')
            ->findUpcomingBySquareCustomerId($customer->square_customer_id)
            ->all();

        $this->set(compact('customer', 'upcomingBookings'));

        return $this->render();
    }
}