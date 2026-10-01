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

        $paymentsTable =
            $this->fetchTable('Payments');


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
        $availableYears =
            $customersTable
                ->find()
                ->select([
                    'square_created_at',
                ])
                ->where([
                    'square_created_at IS NOT' =>
                        null,
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
         * Customer Growth has its own year filter.
         */
        $customerGrowthYear =
            (int)(
                $this->request->getQuery(
                    'customer_growth_year'
                )
                ?? $defaultYear
            );


        if (
            !in_array(
                $customerGrowthYear,
                $availableYears,
                true
            )
        ) {

            $customerGrowthYear =
                $defaultYear;

        }


        /*
 * Booking Trends has its own year filter.
 */
        $bookingTrendYear =
            (int)(
                $this->request->getQuery(
                    'booking_trend_year'
                )
                ?? $defaultYear
            );

        if (
            !in_array(
                $bookingTrendYear,
                $availableYears,
                true
            )
        ) {
            $bookingTrendYear =
                $defaultYear;
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
  * -------------------------------------------------
  * TOTAL INCOME
  * -------------------------------------------------
  */

        /*
         * Total Income has its own filter.
         */
        $incomeRange =
            $this->request->getQuery('income_range')
            ?? 'month';


        switch ($incomeRange) {

            case 'day':

                $incomeStart =
                    $today;

                $incomeEnd =
                    $today->addDays(1);

                break;


            case 'week':

                $incomeStart =
                    $today->startOfWeek();

                $incomeEnd =
                    $incomeStart->addWeeks(1);

                break;


            case 'year':

                $incomeStart =
                    new FrozenTime(
                        $today->format('Y')
                        . '-01-01 00:00:00'
                    );

                $incomeEnd =
                    $incomeStart->addYears(1);

                break;


            case 'month':

            default:

                $incomeRange = 'month';

                $incomeStart =
                    $today->startOfMonth();

                $incomeEnd =
                    $incomeStart->addMonths(1);

                break;
        }


        /*
         * Total income from completed payments
         * during the selected income period.
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
                        'status' =>
                            'completed',

                        'square_created_at >=' =>
                            $incomeStart,

                        'square_created_at <' =>
                            $incomeEnd,
                    ])
                    ->first()
                    ?->total
                ?? 0
            );

        /*
         * -------------------------------------------------
         * CUSTOMER REBOOKING
         * -------------------------------------------------
         */

        /*
         * For now, a customer is considered
         * inactive after 7 days without
         * an accepted booking.
         */
        $inactiveThresholdDays = 7;
        $inactiveMaximumDays = 100;

        $customersForRebooking =
            $customersTable
                ->find()
                ->where([
                    'square_customer_id IS NOT' =>
                        null,
                ])
                ->all();


        $rebookingCustomers = [];


        foreach (
            $customersForRebooking
            as $customer
        ) {

            /*
             * Find the customer's most recent
             * accepted booking.
             */
            $lastBooking =
                $bookingsTable
                    ->find()
                    ->where([
                        'square_customer_id' =>
                            $customer
                                ->square_customer_id,

                        'status' =>
                            'accepted',

                        'start_at IS NOT' =>
                            null,

                        /*
                         * Future appointments should not
                         * count as previous visits.
                         */
                        'start_at <=' =>
                            FrozenTime::now(),
                    ])
                    ->orderBy([
                        'start_at' =>
                            'DESC',
                    ])
                    ->first();


            if ($lastBooking === null) {
                continue;
            }


            $lastBookingTime =
                new FrozenTime(
                    $lastBooking
                        ->start_at
                );


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


            if (
                $daysSinceLastBooking >=
                $inactiveThresholdDays
                &&
                $daysSinceLastBooking <=
                $inactiveMaximumDays
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
         * Longest inactive customers first.
         */
        usort(
            $rebookingCustomers,
            function (
                array $customerA,
                array $customerB
            ): int {

                return
                    $customerB[
                    'daysSinceLastBooking'
                    ]
                    <=>
                    $customerA[
                    'daysSinceLastBooking'
                    ];

            }
        );

        /*
 * -------------------------------------------------
 * CUSTOMER REBOOKING PAGINATION
 * -------------------------------------------------
 */

        $rebookingPerPage = 10;

        $rebookingTotalCustomers =
            count($rebookingCustomers);

        $rebookingTotalPages =
            max(
                1,
                (int)ceil(
                    $rebookingTotalCustomers
                    / $rebookingPerPage
                )
            );

        $rebookingPage =
            max(
                1,
                (int)(
                    $this->request->getQuery(
                        'rebooking_page'
                    )
                    ?? 1
                )
            );

        if ($rebookingPage > $rebookingTotalPages) {
            $rebookingPage =
                $rebookingTotalPages;
        }

        $rebookingOffset =
            ($rebookingPage - 1)
            * $rebookingPerPage;

        $rebookingCustomers =
            array_slice(
                $rebookingCustomers,
                $rebookingOffset,
                $rebookingPerPage
            );


        /*
         * -------------------------------------------------
         * CUSTOMER GROWTH CHART
         * -------------------------------------------------
         */

        $customerGrowthData = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 0,
            8 => 0,
            9 => 0,
            10 => 0,
            11 => 0,
            12 => 0,
        ];


        $customersForGrowth =
            $customersTable
                ->find()
                ->where([
                    'square_created_at >=' =>
                        new FrozenTime(
                            $customerGrowthYear .
                            '-01-01 00:00:00'
                        ),

                    'square_created_at <' =>
                        new FrozenTime(
                            ($customerGrowthYear + 1) .
                            '-01-01 00:00:00'
                        ),
                ])
                ->all();

        foreach (
            $customersForGrowth
            as $customer
        ) {

            if (
                empty(
                $customer
                    ->square_created_at
                )
            ) {
                continue;
            }


            $month =
                (int)$customer
                    ->square_created_at
                    ->format('n');


            $customerGrowthData[
            $month
            ]++;

        }


        $customerGrowthLabels = [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
        ];


        $customerGrowthValues =
            array_values(
                $customerGrowthData
            );


        /*
         * -------------------------------------------------
         * BOOKING TRENDS CHART
         * -------------------------------------------------
         */

        $bookingTrendData = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 0,
            8 => 0,
            9 => 0,
            10 => 0,
            11 => 0,
            12 => 0,
        ];


        $bookingsForTrend =
            $bookingsTable
                ->find()
                ->where([
                    'start_at >=' =>
                        new FrozenTime(
                            $bookingTrendYear .
                            '-01-01 00:00:00'
                        ),

                    'start_at <' =>
                        new FrozenTime(
                            ($bookingTrendYear + 1) .
                            '-01-01 00:00:00'
                        ),

                    'start_at IS NOT' =>
                        null,
                ])
                ->all();


        foreach (
            $bookingsForTrend
            as $booking
        ) {

            if (empty($booking->start_at)) {
                continue;
            }


            $bookingMonth =
                (int)$booking
                    ->start_at
                    ->format('n');


            $bookingTrendData[
            $bookingMonth
            ]++;

        }


        $bookingTrendLabels = [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
        ];


        $bookingTrendValues =
            array_values(
                $bookingTrendData
            );


        /*
         * -------------------------------------------------
         * BOOKING STATUS CHART
         * -------------------------------------------------
         */

        $bookingStatusData = [
            'pending' => 0,
            'cancelled_by_customer' => 0,
            'cancelled_by_seller' => 0,
            'declined' => 0,
            'accepted' => 0,
            'no_show' => 0,
        ];


        $bookingsForStatus =
            $bookingsTable
                ->find()
                ->where([
                    'start_at >=' =>
                        new FrozenTime(
                            $selectedYear .
                            '-01-01 00:00:00'
                        ),

                    'start_at <' =>
                        new FrozenTime(
                            ($selectedYear + 1) .
                            '-01-01 00:00:00'
                        ),

                    'start_at IS NOT' =>
                        null,
                ])
                ->all();


        foreach (
            $bookingsForStatus
            as $booking
        ) {

            $status =
                strtolower(
                    trim(
                        (string)$booking->status
                    )
                );

            $status =
                str_replace(
                    [' ', '-'],
                    '_',
                    $status
                );


            if ($status === 'pending') {

                $bookingStatusData[
                'pending'
                ]++;

            } elseif (
                $status === 'cancelled_by_customer'
            ) {

                $bookingStatusData[
                'cancelled_by_customer'
                ]++;

            } elseif (
                $status === 'cancelled_by_seller'
            ) {

                $bookingStatusData[
                'cancelled_by_seller'
                ]++;

            } elseif (
                $status ===
                'declined'
            ) {

                $bookingStatusData[
                'declined'
                ]++;

            } elseif (
                $status ===
                'accepted'
            ) {

                $bookingStatusData[
                'accepted'
                ]++;

            } elseif (
                $status === 'no_show'
            ) {

                $bookingStatusData[
                'no_show'
                ]++;

            }
        }


        $bookingStatusLabels = [];
        $bookingStatusValues = [];


        $bookingStatusDisplayLabels = [
            'pending' =>
                'Pending',

            'cancelled_by_customer' =>
                'Cancelled By Customer',

            'cancelled_by_seller' =>
                'Cancelled By Seller',

            'declined' =>
                'Declined',

            'accepted' =>
                'Accepted',

            'no_show' =>
                'No Show',
        ];


        foreach (
            $bookingStatusDisplayLabels
            as $statusKey => $statusLabel
        ) {

            if (
                $bookingStatusData[$statusKey] > 0
            ) {

                $bookingStatusLabels[] =
                    $statusLabel;

                $bookingStatusValues[] =
                    $bookingStatusData[
                    $statusKey
                    ];

            }
        }

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
            'totalBookings',
            'totalIncomeCents',
            'availableYears',
            'rebookingCustomers',
            'inactiveThresholdDays',
            'customerGrowthLabels',
            'customerGrowthValues',
            'bookingTrendLabels',
            'bookingTrendValues',
            'bookingStatusLabels',
            'bookingStatusValues',
            'customerGrowthYear',
            'bookingTrendYear',
            'inactiveMaximumDays',
            'rebookingPage',
            'rebookingTotalPages',
            'rebookingTotalCustomers',
            'incomeRange'
        ));


        return $this->render();
    }


    public function sendRebookingReminder(
        string $customerId
    ): Response {

        $this->request
            ->allowMethod([
                'post',
            ]);


        $customersTable =
            $this->fetchTable(
                'Customers'
            );


        $customer =
            $customersTable
                ->get(
                    $customerId
                );


        $bookingsTable =
            $this->fetchTable(
                'Bookings'
            );


        $lastBooking =
            $bookingsTable
                ->find()
                ->where([
                    'square_customer_id' =>
                        $customer
                            ->square_customer_id,

                    'status' =>
                        'accepted',

                    'start_at IS NOT' =>
                        null,

                    'start_at <=' =>
                        FrozenTime::now(),
                ])
                ->orderBy([
                    'start_at' =>
                        'DESC',
                ])
                ->first();


        if ($lastBooking === null) {

            $this->Flash
                ->error(
                    'No previous accepted booking was found for this customer.'
                );


            return $this->redirect([
                'controller' =>
                    'Dashboard',

                'action' =>
                    'index',
            ]);

        }


        $lastBookingTime =
            new FrozenTime(
                $lastBooking
                    ->start_at
            );


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


        $customerName =
            trim(
                (
                    $customer
                        ->given_name
                    ??
                    $customer
                        ->first_name
                    ??
                    ''
                )
                .
                ' '
                .
                (
                    $customer
                        ->family_name
                    ??
                    $customer
                        ->last_name
                    ??
                    ''
                )
            );


        if ($customerName === '') {

            $customerName =
                'Customer';

        }


        /*
         * Decide whether to send to the
         * real customer or the test email.
         */
        $sendReal =
            filter_var(
                env(
                    'REBOOKING_SEND_REAL',
                    'false'
                ),
                FILTER_VALIDATE_BOOLEAN
            );


        $testEmail =
            env(
                'REBOOKING_TEST_EMAIL'
            );


        $customerEmail =
            $customer
                ->email_address
            ??
            $customer
                ->email
            ??
            null;


        if ($sendReal) {

            if (empty($customerEmail)) {

                $this->Flash
                    ->error(
                        'This customer does not have an email address.'
                    );


                return $this->redirect([
                    'controller' =>
                        'Dashboard',

                    'action' =>
                        'index',
                ]);

            }


            $recipientEmail =
                $customerEmail;

        } else {

            if (empty($testEmail)) {

                $this->Flash
                    ->error(
                        'Rebooking test email is not configured.'
                    );


                return $this->redirect([
                    'controller' =>
                        'Dashboard',

                    'action' =>
                        'index',
                ]);

            }


            $recipientEmail =
                $testEmail;

        }


        try {

            $mailer =
                new Mailer(
                    'rebooking_test'
                );


            $mailer
                ->setEmailFormat(
                    'html'
                )
                ->setTo(
                    $recipientEmail
                )
                ->setSubject(
                    'We’d love to see you again'
                )
                ->setViewVars([
                    'customerName' =>
                        $customerName,

                    'daysSinceLastBooking' =>
                        $daysSinceLastBooking,
                ]);


            $mailer
                ->viewBuilder()
                ->setTemplate(
                    'rebooking_reminder'
                );


            $mailer
                ->deliver();


            if ($sendReal) {

                $this->Flash
                    ->success(
                        'Rebooking reminder sent successfully.'
                    );

            } else {

                $this->Flash
                    ->success(
                        'Test rebooking reminder sent successfully.'
                    );

            }

        } catch (\Throwable $e) {

            \Cake\Log\Log::error(
                'Rebooking reminder failed: '
                .
                $e->getMessage()
            );


            $this->Flash
                ->error(
                    'Unable to send the rebooking reminder.'
                );

        }


        return $this->redirect([
            'controller' =>
                'Dashboard',

            'action' =>
                'index',
        ]);
    }
}
