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
            'recentCustomers'
        ));


        return $this->render();
    }


    /**
     * Export customers.
     */
    public function exportCustomers(): Response
    {
        $customersTable =
            $this->fetchTable('Customers');


        $range =
            $this->request->getQuery('range')
            ?? 'day';

        $startDate =
            $this->request->getQuery('start_date');

        $endDate =
            $this->request->getQuery('end_date');

        $today =
            FrozenTime::now()->startOfDay();


        switch ($range) {

            case 'week':

                $periodStart =
                    $today->startOfWeek();

                $periodEnd =
                    $periodStart->addWeeks(1);

                break;


            case 'month':

                $periodStart =
                    $today->startOfMonth();

                $periodEnd =
                    $periodStart->addMonths(1);

                break;


            case 'year':

                $periodStart =
                    $today->startOfYear();

                $periodEnd =
                    $periodStart->addYears(1);

                break;


            case 'custom':

                if (
                    !empty($startDate) &&
                    !empty($endDate)
                ) {

                    $periodStart =
                        new FrozenTime(
                            $startDate .
                            ' 00:00:00'
                        );

                    $periodEnd =
                        (
                        new FrozenTime(
                            $endDate .
                            ' 00:00:00'
                        )
                        )
                            ->addDays(1);

                } else {

                    $periodStart =
                        $today;

                    $periodEnd =
                        $today->addDays(1);

                }

                break;


            case 'day':
            default:

                $periodStart =
                    $today;

                $periodEnd =
                    $today->addDays(1);

                break;
        }


        /*
         * Export customers inside
         * the selected period.
         */
        $customers =
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
                ->all();


        $csv =
            fopen(
                'php://temp',
                'r+'
            );


        fputcsv(
            $csv,
            [
                'Customer',
                'Email',
                'Phone',
                'Account Created',
            ]
        );


        foreach ($customers as $customer) {

            $name =
                trim(
                    ($customer->given_name ?? '')
                    . ' '
                    . ($customer->family_name ?? '')
                );


            fputcsv(
                $csv,
                [
                    $name,

                    $customer
                        ->email_address
                    ?? '',

                    $customer
                        ->phone_number
                    ?? '',

                    $customer
                        ->square_created_at
                        ? $customer
                        ->square_created_at
                        ->format(
                            'd/m/Y H:i:s'
                        )
                        : '',
                ]
            );
        }


        rewind($csv);

        $content =
            stream_get_contents($csv);

        fclose($csv);


        $filename =
            $range === 'custom'
                ? 'customers-custom.csv'
                : 'customers-' .
                $range .
                '.csv';


        return $this
            ->response
            ->withType('csv')
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="' .
                $filename .
                '"'
            )
            ->withStringBody(
                $content
            );
    }
}
