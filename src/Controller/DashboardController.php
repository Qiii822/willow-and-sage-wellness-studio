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

        // Selected date
        $selectedDate = $this->request->getQuery('date')
            ?? FrozenTime::now()->format('Y-m-d');

        // Selected range
        $range = $this->request->getQuery('range')
            ?? 'day';

        $selectedTime = new FrozenTime(
            $selectedDate . ' 00:00:00'
        );


        /*
         * Work out the current period
         * and previous period.
         */
        switch ($range) {

            case 'week':

                $periodStart = $selectedTime->startOfWeek();
                $periodEnd = $periodStart->addWeeks(1);

                $previousPeriodStart =
                    $periodStart->subWeeks(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            case 'month':

                $periodStart = $selectedTime->startOfMonth();
                $periodEnd = $periodStart->addMonths(1);

                $previousPeriodStart =
                    $periodStart->subMonths(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            case 'year':

                $periodStart = $selectedTime->startOfYear();
                $periodEnd = $periodStart->addYears(1);

                $previousPeriodStart =
                    $periodStart->subYears(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            case 'day':
            default:

                $range = 'day';

                $periodStart = $selectedTime->startOfDay();
                $periodEnd = $periodStart->addDays(1);

                $previousPeriodStart =
                    $periodStart->subDays(1);

                $previousPeriodEnd =
                    $periodStart;

                break;
        }


        /*
         * Total customers that existed
         * by the end of the selected period.
         */
        $totalCustomers = $customersTable
            ->find()
            ->where([
                'square_created_at <' => $periodEnd,
            ])
            ->count();


        /*
         * Customers created during
         * the selected period.
         */
        $customersSelectedDate = $customersTable
            ->find()
            ->where([
                'square_created_at >=' => $periodStart,
                'square_created_at <' => $periodEnd,
            ])
            ->count();


        /*
         * Customers created during
         * the previous equivalent period.
         */
        $previousCustomers = $customersTable
            ->find()
            ->where([
                'square_created_at >=' => $previousPeriodStart,
                'square_created_at <' => $previousPeriodEnd,
            ])
            ->count();


        /*
         * Percentage change compared with
         * the previous period.
         */
        if ($previousCustomers > 0) {

            $customerChangePercent =
                (($customersSelectedDate - $previousCustomers)
                    / $previousCustomers) * 100;

        } elseif ($customersSelectedDate > 0) {

            // There were no customers previously,
            // but there are customers now.
            $customerChangePercent = 100;

        } else {

            $customerChangePercent = 0;
        }

        $customerChangePercent =
            round($customerChangePercent, 1);


        /*
         * Recent customers that existed
         * by the end of the selected period.
         */
        $recentCustomers = $customersTable
            ->find()
            ->where([
                'square_created_at <' => $periodEnd,
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
            'range',
            'periodStart',
            'periodEnd',
            'previousPeriodStart',
            'previousPeriodEnd',
            'totalCustomers',
            'customersSelectedDate',
            'previousCustomers',
            'customerChangePercent',
            'recentCustomers'
        ));

        return $this->render();
    }
}
