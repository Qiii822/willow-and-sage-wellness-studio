<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\ORM\Query\SelectQuery;

class CustomersController extends AppController
{
    // The customer list is staff-only.
    protected bool $adminOnly = true;

    /**
     * Which set of customers the page is showing.
     */
    protected const SOURCES = ['square', 'registered'];

    public function index(): Response
    {
        $source = (string)$this->request->getQuery('source', 'square');

        if (!in_array($source, self::SOURCES, true)) {
            $source = 'square';
        }

        $squareCount = $this->squareCustomers()->count();
        $registeredCount = $this->registeredCustomers()->count();

        $query = $source === 'registered'
            ? $this->registeredCustomers()->orderBy(['Users.created_at' => 'DESC'])
            : $this->squareCustomers()->orderBy(['Customers.square_created_at' => 'DESC']);

        $limit = 10;

        /*
         * Clamp the requested page before paginating.
         */
        $total = $source === 'registered' ? $registeredCount : $squareCount;
        $maxPage = max(1, (int)ceil($total / $limit));
        $requestedPage = (int)$this->request->getQuery('page', 1);

        if ($requestedPage < 1) {
            return $this->redirect([
                'action' => 'index',
                '?' => ['source' => $source],
            ]);
        }

        if ($requestedPage > $maxPage) {
            return $this->redirect([
                'action' => 'index',
                '?' => [
                    'source' => $source,
                    'page' => $maxPage,
                ],
            ]);
        }

        $customers = $this->paginate($query, [
            'limit' => $limit,
        ]);

        $this->set(compact(
            'customers',
            'source',
            'squareCount',
            'registeredCount'
        ));

        return $this->render();
    }

    /**
     * Customers synced from Square, excluding ones Square no longer reports.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    protected function squareCustomers(): SelectQuery
    {
        return $this->fetchTable('Customers')
            ->find()
            ->where(['Customers.deleted_at IS' => null]);
    }

    /**
     * People who registered on the site and have no Square record yet.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    protected function registeredCustomers(): SelectQuery
    {
        return $this->fetchTable('Users')
            ->find()
            ->where([
                'Users.roles' => 'user',
                'Users.square_customer_id IS' => null,
            ]);
    }

    /**
     * Displays a single customer with their upcoming bookings.
     *
     * @param string|null $id Customer id.
     * @return \Cake\Http\Response
     */
    public function view(?string $id = null): Response
    {
        $customer = $this->fetchTable('Customers')->get($id);

        $upcomingBookings = $this->fetchTable('Bookings')
            ->findUpcomingBySquareCustomerId($customer->square_customer_id)
            ->all();

        $this->set(compact('customer', 'upcomingBookings'));

        return $this->render();
    }
}
