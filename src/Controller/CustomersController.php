<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\BookingAccessCodesTable;
use Cake\Http\Response;

class CustomersController extends AppController
{
    // The customer list is staff-only.
    protected bool $adminOnly = true;

    /** People per page. */
    protected const PER_PAGE = 10;

    /**
     * Everyone Square knows about, one row per person.
     *
     * There used to be two tabs here, "From Square" and "Registered on
     * site". Nobody can register on this site - there are no customer
     * accounts, only bookings - so the second tab counted staff logins and
     * called them customers. Square is the only source now.
     *
     * Square keys a customer on their email, so one person who books twice
     * with different addresses becomes two customers. The phone number is
     * what actually identifies a person to the studio - it is what they are
     * rung on - so records sharing a number are shown as one person, with
     * their bookings added together.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        $people = $this->peopleByPhone();

        $total = count($people);
        $maxPage = max(1, (int)ceil($total / self::PER_PAGE));
        $page = (int)$this->request->getQuery('page', 1);

        if ($page < 1) {
            return $this->redirect(['action' => 'index']);
        }

        if ($page > $maxPage) {
            return $this->redirect(['action' => 'index', '?' => ['page' => $maxPage]]);
        }

        $this->set('people', array_slice($people, ($page - 1) * self::PER_PAGE, self::PER_PAGE));
        $this->set(compact('total', 'page', 'maxPage'));
        $this->set('first', $total === 0 ? 0 : (($page - 1) * self::PER_PAGE) + 1);
        $this->set('last', min($page * self::PER_PAGE, $total));

        return $this->render();
    }

    /**
     * Square's customers, collapsed into people.
     *
     * Grouped in PHP rather than in SQL because matching a phone number
     * means normalising it first - the same number arrives as "0412 345
     * 678", "+61412345678" and "0412345678" - and that is not something the
     * database can do without a query nobody could read afterwards. The
     * studio has hundreds of customers, not millions, so the whole list is
     * small enough to sort out in memory.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function peopleByPhone(): array
    {
        $customers = $this->fetchTable('Customers')->find()
            ->where(['Customers.deleted_at IS' => null])
            ->orderByDesc('Customers.square_created_at')
            ->all();

        $bookingCounts = $this->bookingCountsBySquareId();
        $people = [];

        foreach ($customers as $customer) {
            $digits = BookingAccessCodesTable::digits($customer->phone_number);

            /*
             * A customer with no number cannot be matched to anyone, so each
             * one stays on its own row. Lumping them together under a blank
             * key would merge strangers into a single person.
             */
            $key = $digits !== '' ? 'phone:' . $digits : 'id:' . $customer->id;

            if (!isset($people[$key])) {
                $people[$key] = [
                    // The newest record's details, because the list is
                    // ordered newest first and a person's latest booking
                    // carries the name and address they used last.
                    'customer' => $customer,
                    'records' => 0,
                    'bookings' => 0,
                    'emails' => [],
                ];
            }

            $people[$key]['records']++;
            $people[$key]['bookings'] += $bookingCounts[$customer->square_customer_id] ?? 0;

            $email = trim((string)$customer->email_address);

            if ($email !== '' && !in_array($email, $people[$key]['emails'], true)) {
                $people[$key]['emails'][] = $email;
            }
        }

        return array_values($people);
    }

    /**
     * How many bookings each Square customer id has.
     *
     * One query rather than one per customer.
     *
     * @return array<string, int>
     */
    protected function bookingCountsBySquareId(): array
    {
        $rows = $this->fetchTable('Bookings')->find()
            ->select([
                'square_customer_id',
                'total' => 'COUNT(*)',
            ])
            ->where(['square_customer_id IS NOT' => null])
            ->groupBy('square_customer_id')
            ->disableHydration()
            ->all();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string)$row['square_customer_id']] = (int)$row['total'];
        }

        return $counts;
    }

    /**
     * One person, with every booking made under any of their records.
     *
     * The id in the address is one Square record, but the page is about the
     * person behind it - so the bookings shown are those of every record
     * sharing their number, not just the one that was clicked.
     *
     * @param string|null $id Customer id.
     * @return \Cake\Http\Response
     */
    public function view(?string $id = null): Response
    {
        $customer = $this->fetchTable('Customers')->get($id);
        $squareIds = $this->squareIdsSharingPhone($customer);

        $upcomingBookings = $this->fetchTable('Bookings')->find()
            ->where([
                'square_customer_id IN' => $squareIds,
                'start_at >=' => \Cake\I18n\DateTime::now(),
            ])
            ->orderByAsc('start_at')
            ->all();

        $this->set(compact('customer', 'upcomingBookings'));
        $this->set('recordCount', count($squareIds));

        return $this->render();
    }

    /**
     * Every Square customer id belonging to the same person.
     *
     * @param \Cake\Datasource\EntityInterface $customer
     * @return array<int, string>
     */
    protected function squareIdsSharingPhone($customer): array
    {
        $digits = BookingAccessCodesTable::digits($customer->phone_number);

        if ($digits === '') {
            return [(string)$customer->square_customer_id];
        }

        $ids = [];

        $rows = $this->fetchTable('Customers')->find()
            ->select(['square_customer_id', 'phone_number'])
            ->where([
                'deleted_at IS' => null,
                'phone_number IS NOT' => null,
            ])
            ->disableHydration()
            ->all();

        foreach ($rows as $row) {
            if (BookingAccessCodesTable::digits($row['phone_number']) === $digits) {
                $ids[] = (string)$row['square_customer_id'];
            }
        }

        return $ids !== [] ? $ids : [(string)$customer->square_customer_id];
    }
}
