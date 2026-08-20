<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\I18n\FrozenTime;

class DashboardController extends AppController
{
    public function index(): Response
    {
        $identity = $this->request->getAttribute('identity');
        $customersTable = $this->fetchTable('Customers');

        // Selected dashboard date.
        // If no date is selected, use today.
        $selectedDate = $this->request->getQuery('date')
            ?? FrozenTime::now()->format('Y-m-d');

        // Start and end of selected day
        $dayStart = new FrozenTime($selectedDate . ' 00:00:00');
        $dayEnd = $dayStart->addDays(1);

        // Total customers that existed by the selected date
        $totalCustomers = $customersTable
            ->find()
            ->where([
                'square_created_at <' => $dayEnd,
            ])
            ->count();

        // Customers created on the selected date only
        $customersSelectedDate = $customersTable
            ->find()
            ->where([
                'square_created_at >=' => $dayStart,
                'square_created_at <' => $dayEnd,
            ])
            ->count();

        // Customers that existed by the selected date
        // Newest first
        $recentCustomers = $customersTable
            ->find()
            ->where([
                'square_created_at <' => $dayEnd,
            ])
            ->orderBy([
                'square_created_at' => 'DESC',
            ])
            ->limit(10)
            ->all();

        // Send data to dashboard
        $this->set(compact(
            'identity',
            'selectedDate',
            'totalCustomers',
            'customersSelectedDate',
            'recentCustomers'
        ));

        return $this->render();
    }
}
