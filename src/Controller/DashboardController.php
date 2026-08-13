<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\I18n\FrozenTime;

class DashboardController extends AppController
{
    public function index(): Response
    {
        $customersTable = $this->fetchTable('Customers');

        // Total number of customers
        $totalCustomers = $customersTable
            ->find()
            ->count();

        // Beginning of this week
        $thisWeekStart = FrozenTime::now()->startOfWeek();

        // Beginning of next week
        $nextWeekStart = $thisWeekStart->addDays(7);

        // Beginning of last week
        $lastWeekStart = $thisWeekStart->subDays(7);

        // Customers created this week
        $customersThisWeek = $customersTable
            ->find()
            ->where([
                'square_created_at >=' => $thisWeekStart,
                'square_created_at <' => $nextWeekStart,
            ])
            ->count();

        // Customers created last week
        $customersLastWeek = $customersTable
            ->find()
            ->where([
                'square_created_at >=' => $lastWeekStart,
                'square_created_at <' => $thisWeekStart,
            ])
            ->count();

        // Weekly growth percentage
        if ($customersLastWeek > 0) {
            $weeklyGrowth =
                (($customersThisWeek - $customersLastWeek)
                    / $customersLastWeek) * 100;
        } elseif ($customersThisWeek > 0) {
            $weeklyGrowth = 100;
        } else {
            $weeklyGrowth = 0;
        }

        $recentCustomers = $customersTable
            ->find()
            ->orderBy([
                'square_created_at' => 'DESC'
            ])
            ->all();

        // Send data to dashboard
        $this->set(compact(
            'totalCustomers',
            'customersThisWeek',
            'customersLastWeek',
            'weeklyGrowth',
            'recentCustomers'
        ));

        return $this->render();
    }
}
