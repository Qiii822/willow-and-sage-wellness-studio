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
}