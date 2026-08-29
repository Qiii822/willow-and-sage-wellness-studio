<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\I18n\FrozenTime;
use Cake\Mailer\Mailer;

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
         * Find years that actually contain
         * customer data.
         */
        $availableYears = $customersTable
            ->find()
            ->select(['square_created_at'])
            ->where([
                'square_created_at IS NOT' => null,
            ])
            ->all()
            ->map(function ($customer) {
                return (int)$customer
                    ->square_created_at
                    ->format('Y');
            })
            ->unique()
            ->toList();

        rsort($availableYears);


        /*
         * Default to the newest year
         * that actually has data.
         */
        $defaultYear =
            $availableYears[0]
            ?? (int)$today->format('Y');


        if (
            empty($selectedYear) ||
            !in_array(
                (int)$selectedYear,
                $availableYears,
                true
            )
        ) {

            $selectedYear =
                $defaultYear;

        } else {

            $selectedYear =
                (int)$selectedYear;

        }


        /*
         * Work out current and previous periods.
         */
        switch ($range) {

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
                    $periodStart
                        ->addDays(1);

                $previousPeriodStart =
                    $periodStart
                        ->subDays(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            case 'week':

                if (empty($selectedWeek)) {

                    $selectedWeek =
                        $today->format(
                            'o-\WW'
                        );

                }

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
                        $today
                            ->startOfWeek();

                }

                $periodEnd =
                    $periodStart
                        ->addWeeks(1);

                $previousPeriodStart =
                    $periodStart
                        ->subWeeks(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            case 'month':

                $selectedMonth =
                    (int)(
                    $selectedMonth
                        ?: $today->format('n')
                    );

                if (
                    $selectedMonth < 1 ||
                    $selectedMonth > 12
                ) {

                    $selectedMonth =
                        (int)$today->format('n');

                }

                $periodStart =
                    new FrozenTime(
                        sprintf(
                            '%04d-%02d-01 00:00:00',
                            $selectedYear,
                            $selectedMonth
                        )
                    );

                $periodEnd =
                    $periodStart
                        ->addMonths(1);

                $previousPeriodStart =
                    $periodStart
                        ->subMonths(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            case 'year':

                $periodStart =
                    new FrozenTime(
                        $selectedYear .
                        '-01-01 00:00:00'
                    );

                $periodEnd =
                    $periodStart
                        ->addYears(1);

                $previousPeriodStart =
                    $periodStart
                        ->subYears(1);

                $previousPeriodEnd =
                    $periodStart;

                break;


            case 'custom':

                if (
                    !empty($startDate) &&
                    !empty($endDate)
                ) {

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

                    $periodEnd =
                        (
                        new FrozenTime(
                            $endDate .
                            ' 00:00:00'
                        )
                        )
                            ->addDays(1);

                    $periodSeconds =
                        $periodEnd
                            ->getTimestamp()
                        -
                        $periodStart
                            ->getTimestamp();

                    $previousPeriodEnd =
                        $periodStart;

                    $previousPeriodStart =
                        $periodStart
                            ->subSeconds(
                                $periodSeconds
                            );

                } else {

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


        /*
         * -------------------------------------------------
         * CUSTOMER REBOOKING / BOOKING HISTORY
         * -------------------------------------------------
         */

        /*
         * For now, a customer is considered
         * inactive after 7 days without
         * an accepted booking.
         */
        $inactiveThresholdDays = 7;


        /*
         * Get customers that have a Square customer ID.
         */
        $customersForRebooking =
            $customersTable
                ->find()
                ->where([
                    'square_customer_id IS NOT' => null,
                ])
                ->all();


        $rebookingCustomers = [];


        foreach ($customersForRebooking as $customer) {

            /*
             * Find the customer's most recent
             * accepted booking.
             */
            $lastBooking =
                $bookingsTable
                    ->find()
                    ->where([
                        'square_customer_id' =>
                            $customer->square_customer_id,

                        'status' =>
                            'accepted',

                        'start_at IS NOT' =>
                            null,

                        /*
                         * Do not treat future
                         * appointments as a previous visit.
                         */
                        'start_at <=' =>
                            FrozenTime::now(),
                    ])
                    ->orderBy([
                        'start_at' =>
                            'DESC',
                    ])
                    ->first();


            /*
             * If the customer has never had
             * an accepted booking, skip them
             * for now.
             */
            if ($lastBooking === null) {
                continue;
            }


            $lastBookingTime =
                new FrozenTime(
                    $lastBooking->start_at
                );


            /*
             * Calculate full days since
             * the customer's last booking.
             */
            $daysSinceLastBooking =
                (int)floor(
                    (
                        FrozenTime::now()
                            ->getTimestamp()
                        -
                        $lastBookingTime
                            ->getTimestamp()
                    )
                    / 86400
                );


            /*
             * Only include customers that
             * have passed the inactive threshold.
             */
            if (
                $daysSinceLastBooking >=
                $inactiveThresholdDays
            ) {

                $rebookingCustomers[] = [
                    'customer' =>
                        $customer,

                    'lastBooking' =>
                        $lastBooking,

                    'daysSinceLastBooking' =>
                        $daysSinceLastBooking,
                ];

            }
        }


        /*
         * Customers who have been inactive
         * for the longest appear first.
         */
        usort(
            $rebookingCustomers,
            function (
                array $customerA,
                array $customerB
            ): int {

                return
                    $customerB['daysSinceLastBooking']
                    <=>
                    $customerA['daysSinceLastBooking'];

            }
        );


        /*
         * Send data to view.
         */
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
            'recentCustomers',
            'availableYears',
            'rebookingCustomers',
            'inactiveThresholdDays'
        ));


        return $this->render();
    }

    public function sendRebookingReminder(string $customerId): Response
    {
        $this->request->allowMethod(['post']);

        $customersTable = $this->fetchTable('Customers');

        $customer = $customersTable->get($customerId);

        $customerName = trim(
            ($customer->given_name ?? $customer->first_name ?? '')
            . ' '
            . ($customer->family_name ?? $customer->last_name ?? '')
        );

        if ($customerName === '') {
            $customerName = 'Customer';
        }

        // Development safety:
        // Never send to the real customer's email address.
        $testEmail = env('REBOOKING_TEST_EMAIL');

        if (empty($testEmail)) {
            $this->Flash->error(
                'Rebooking test email is not configured.'
            );

            return $this->redirect([
                'controller' => 'Dashboard',
                'action' => 'index',
            ]);
        }

        try {
            $mailer = new Mailer('rebooking_test');

            $mailer
                ->setEmailFormat('text')
                ->setTo($testEmail)
                ->setSubject('Test Rebooking Reminder')
                ->setBodyText(
                    "Hi {$customerName},\n\n"
                    . "It's been a while since your last appointment.\n\n"
                    . "We'd love to welcome you back.\n\n"
                    . "This is a development test rebooking reminder."
                );

            $mailer->deliver();

            $this->Flash->success(
                'Test rebooking reminder sent successfully.'
            );
        } catch (\Throwable $e) {
            \Cake\Log\Log::error(
                'Rebooking reminder failed: ' . $e->getMessage()
            );

            $this->Flash->error(
                'Unable to send the test rebooking reminder.'
            );
        }

        return $this->redirect([
            'controller' => 'Dashboard',
            'action' => 'index',
        ]);
    }
}
