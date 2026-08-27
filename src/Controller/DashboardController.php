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


        /*
         * Selected range.
         */
        $range =
            $this->request->getQuery('range')
            ?? 'today';


        /*
         * Filter values.
         */
        $selectedDay =
            $this->request->getQuery('day');

        $selectedWeek =
            $this->request->getQuery('week');

        $selectedYear =
            $this->request->getQuery('selected_year')
            ?? $this->request->getQuery('month_year');

        $selectedMonth =
            $this->request->getQuery('month');

        $startDate =
            $this->request->getQuery('start_date');

        $endDate =
            $this->request->getQuery('end_date');


        $today =
            FrozenTime::now()
                ->startOfDay();


        /*
         * Work out current and previous periods.
         */
        switch ($range) {

            /*
             * Today
             */
            case 'today':

                $periodStart =
                    $today;

                $periodEnd =
                    $today->addDays(1);

                $previousPeriodStart =
                    $today->subDays(1);

                $previousPeriodEnd =
                    $today;

                break;


            /*
             * Daily
             */
            case 'day':

                if (empty($selectedDay)) {

                    $selectedDay =
                        $today->format('Y-m-d');

                }


                $periodStart =
                    new FrozenTime(
                        $selectedDay .
                        ' 00:00:00'
                    );


                $periodEnd =
                    $periodStart->addDays(1);


                $previousPeriodStart =
                    $periodStart->subDays(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            /*
             * Weekly
             */
            case 'week':

                if (empty($selectedWeek)) {

                    $selectedWeek =
                        $today->format(
                            'o-\WW'
                        );

                }


                /*
                 * Expected format:
                 * 2026-W35
                 */
                if (
                    preg_match(
                        '/^(\d{4})-W(\d{2})$/',
                        $selectedWeek,
                        $matches
                    )
                ) {

                    $weekYear =
                        (int)$matches[1];

                    $weekNumber =
                        (int)$matches[2];


                    $periodStart =
                        FrozenTime::now()
                            ->setISODate(
                                $weekYear,
                                $weekNumber,
                                1
                            )
                            ->startOfDay();

                } else {

                    $periodStart =
                        $today->startOfWeek();

                }


                $periodEnd =
                    $periodStart->addWeeks(1);


                $previousPeriodStart =
                    $periodStart->subWeeks(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            /*
             * Monthly
             */
            case 'month':

                $selectedYear =
                    (int)(
                    $selectedYear
                        ?: $today->format('Y')
                    );


                $selectedMonth =
                    (int)(
                    $selectedMonth
                        ?: $today->format('n')
                    );


                $periodStart =
                    new FrozenTime(
                        sprintf(
                            '%04d-%02d-01 00:00:00',
                            $selectedYear,
                            $selectedMonth
                        )
                    );


                $periodEnd =
                    $periodStart->addMonths(1);


                $previousPeriodStart =
                    $periodStart->subMonths(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            /*
             * Yearly
             */
            case 'year':

                $selectedYear =
                    (int)(
                    $selectedYear
                        ?: $today->format('Y')
                    );


                $periodStart =
                    new FrozenTime(
                        $selectedYear .
                        '-01-01 00:00:00'
                    );


                $periodEnd =
                    $periodStart->addYears(1);


                $previousPeriodStart =
                    $periodStart->subYears(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            /*
             * Custom range
             */
            case 'custom':

                if (
                    !empty($startDate) &&
                    !empty($endDate)
                ) {

                    /*
                     * If dates are reversed,
                     * swap them.
                     */
                    if ($startDate > $endDate) {

                        [
                            $startDate,
                            $endDate
                        ] = [
                            $endDate,
                            $startDate
                        ];

                    }


                    $periodStart =
                        new FrozenTime(
                            $startDate .
                            ' 00:00:00'
                        );


                    /*
                     * Add one day so the selected
                     * end date is inclusive.
                     */
                    $periodEnd =
                        (
                        new FrozenTime(
                            $endDate .
                            ' 00:00:00'
                        )
                        )
                            ->addDays(1);


                    $periodSeconds =
                        $periodEnd->getTimestamp()
                        -
                        $periodStart->getTimestamp();


                    $previousPeriodEnd =
                        $periodStart;


                    $previousPeriodStart =
                        $periodStart
                            ->subSeconds(
                                $periodSeconds
                            );

                } else {

                    /*
                     * Custom selected but dates
                     * have not been entered yet.
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


            /*
             * Invalid range falls back to Today.
             */
            default:

                $range =
                    'today';

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
         * by the end of the selected period.
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
         * the selected period.
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
         * Customers in previous period.
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
         * Recent customers from the
         * selected period only.
         */
        $recentCustomers =
            $customersTable
                ->find()
                ->where([
                    'square_created_at >=' =>
                        $periodStart,

                    'square_created_at <' =>
                        $periodEnd,
                ])
                ->orderBy([
                    'square_created_at' =>
                        'DESC',
                ])
                ->limit(10)
                ->all();


        $this->set(compact(
            'identity',
            'range',
            'selectedDay',
            'selectedWeek',
            'selectedYear',
            'selectedMonth',
            'startDate',
            'endDate',
            'periodStart',
            'periodEnd',
            'previousPeriodStart',
            'previousPeriodEnd',
            'totalCustomers',
            'customersSelectedDate',
            'previousCustomers',
            'recentCustomers'
        ));


        return $this->render();
    }
}
