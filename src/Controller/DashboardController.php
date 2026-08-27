<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\I18n\FrozenTime;

class DashboardController extends AppController
{
    public function index(): Response
    {
        $identity =
            $this->request->getAttribute('identity');

        $customersTable =
            $this->fetchTable('Customers');

        $bookingsTable =
            $this->fetchTable('Bookings');

        $paymentsTable =
            $this->fetchTable('Payments');


        /*
         * Selected range
         */
        $range =
            $this->request->getQuery('range')
            ?? 'day';


        /*
         * Custom dates
         */
        $startDate =
            $this->request->getQuery('start_date');

        $endDate =
            $this->request->getQuery('end_date');


        /*
         * Today's date is used for
         * Daily / Weekly / Monthly / Yearly.
         */
        $today =
            FrozenTime::now()
                ->startOfDay();


        /*
         * Work out the selected period.
         */
        switch ($range) {

            case 'week':

                $periodStart =
                    $today->startOfWeek();

                $periodEnd =
                    $periodStart->addWeeks(1);

                $previousPeriodStart =
                    $periodStart->subWeeks(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            case 'month':

                $periodStart =
                    $today->startOfMonth();

                $periodEnd =
                    $periodStart->addMonths(1);

                $previousPeriodStart =
                    $periodStart->subMonths(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            case 'year':

                $periodStart =
                    $today->startOfYear();

                $periodEnd =
                    $periodStart->addYears(1);

                $previousPeriodStart =
                    $periodStart->subYears(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            case 'custom':

                /*
                 * If both dates are provided,
                 * use the user's custom range.
                 */
                if (
                    !empty($startDate) &&
                    !empty($endDate)
                ) {

                    $periodStart =
                        new FrozenTime(
                            $startDate .
                            ' 00:00:00'
                        );

                    /*
                     * Add one day so the selected
                     * end date is included.
                     */
                    $periodEnd =
                        (
                        new FrozenTime(
                            $endDate .
                            ' 00:00:00'
                        )
                        )
                            ->addDays(1);


                    /*
                     * Length of custom period.
                     */
                    $periodSeconds =
                        $periodEnd->getTimestamp()
                        -
                        $periodStart->getTimestamp();


                    /*
                     * Previous range uses the same
                     * number of seconds immediately
                     * before the selected range.
                     */
                    $previousPeriodEnd =
                        $periodStart;

                    $previousPeriodStart =
                        $periodStart->subSeconds(
                            $periodSeconds
                        );

                } else {

                    /*
                     * No custom dates selected yet.
                     * Default to today.
                     */
                    $periodStart =
                        $today;

                    $periodEnd =
                        $today->addDays(1);

                    $previousPeriodStart =
                        $today->subDays(1);

                    $previousPeriodEnd =
                        $today;

                }

                break;


            case 'day':
            default:

                $range = 'day';

                $periodStart =
                    $today;

                $periodEnd =
                    $today->addDays(1);

                $previousPeriodStart =
                    $today->subDays(1);

                $previousPeriodEnd =
                    $today;

                break;
        }


        /*
         * Total customers that existed
         * by the end of the selected range.
         */
        $totalCustomers =
            $customersTable
                ->find()
                ->where([
                    'square_created_at <' =>
                        $periodEnd,
                ])
                ->count();


        /*
         * Customers created inside
         * the selected range.
         */
        $customersSelectedDate =
            $customersTable
                ->find()
                ->where([
                    'square_created_at >=' =>
                        $periodStart,

                    'square_created_at <' =>
                        $periodEnd,
                ])
                ->count();


        /*
         * Previous period customer count.
         */
        $previousCustomers =
            $customersTable
                ->find()
                ->where([
                    'square_created_at >=' =>
                        $previousPeriodStart,

                    'square_created_at <' =>
                        $previousPeriodEnd,
                ])
                ->count();


        /*
         * Recent customers up to the
         * selected period end.
         */
        $recentCustomers =
            $customersTable
                ->find()
                ->where([
                    'square_created_at <' =>
                        $periodEnd,
                ])
                ->orderBy([
                    'square_created_at' =>
                        'DESC',
                ])
                ->limit(10)
                ->all();


        /*
         * Total bookings that existed
         * by the end of the selected range.
         */
        $totalBookings =
            $bookingsTable
                ->find()
                ->where([
                    'square_created_at <' =>
                        $periodEnd,
                ])
                ->count();


        /*
         * Total income (sum of completed
         * payments) by the end of the
         * selected range. Stored in the
         * smallest denomination (e.g. cents) -
         * currency is always AUD.
         */
        $totalIncomeCents =
            (int)(
                $paymentsTable
                    ->find()
                    ->select([
                        'total' =>
                            $paymentsTable
                                ->find()
                                ->func()
                                ->sum('amount'),
                    ])
                    ->where([
                        'status' => 'completed',

                        'square_created_at <' =>
                            $periodEnd,
                    ])
                    ->first()
                    ?->total
                ?? 0
            );


        $this->set(compact(
            'identity',
            'range',
            'startDate',
            'endDate',
            'periodStart',
            'periodEnd',
            'previousPeriodStart',
            'previousPeriodEnd',
            'totalCustomers',
            'customersSelectedDate',
            'previousCustomers',
            'recentCustomers',
            'totalBookings',
            'totalIncomeCents'
        ));


        return $this->render();
    }

}
