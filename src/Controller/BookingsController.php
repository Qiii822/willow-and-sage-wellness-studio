<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

class BookingsController extends AppController
{
    public function index(): Response
    {
        $bookings = $this->fetchTable('Bookings')
            ->find()
            ->contain(['Customers'])
            ->orderBy([
                'start_at' => 'DESC',
            ])
            ->all();

        $this->set(compact('bookings'));

        return $this->render();
    }
}
