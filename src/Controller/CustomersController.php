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
     *
     * The two sources live in different tables with different columns, and
     * are deliberately paginated separately rather than merged: a UNION of
     * the two aligns columns by position, which silently pairs `users`
     * columns with the wrong `customers` ones, and LIMIT applies to only the
     * first branch so the paging comes out wrong as well.
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
         * Clamp the requested page before paginating. Without this an
         * out-of-range ?page= raises PageOutOfBoundsException, which renders
         * an error page (and, with debug on, a stack trace) for what is really
         * just a stale or hand-edited URL.
         */
        $total = $source === 'registered' ? $registeredCount : $squareCount;
        $maxPage = max(1, (int)ceil($total / $limit));
        $requestedPage = (int)$this->request->getQuery('page', 1);

        if ($requestedPage < 1) {
            return $this->redirect(['action' => 'index', '?' => ['source' => $source]]);
        }

        if ($requestedPage > $maxPage) {
            return $this->redirect([
                'action' => 'index',
                '?' => ['source' => $source, 'page' => $maxPage],
            ]);
        }

        // Paginate rather than rendering every customer in one page.
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
     * Linked accounts are excluded because they already appear in the Square
     * list, and staff are not customers, so only the `user` role counts.
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
}
