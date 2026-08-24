<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query\SelectQuery;

class DashboardController extends AppController
{
    // Business metrics and customer records: staff accounts only.
    protected bool $adminOnly = true;

    public function index(): Response
    {
        $identity = $this->request->getAttribute('identity');

        // Selected dashboard date.
        // If no date is selected, use today.
        $selectedDate = $this->request->getQuery('date')
            ?? FrozenTime::now()->format('Y-m-d');

        // Start and end of selected day
        $dayStart = new FrozenTime($selectedDate . ' 00:00:00');
        $dayEnd = $dayStart->addDays(1);

        $totalCustomers =
            $this->squareCustomers()
                ->where(['Customers.square_created_at <' => $dayEnd])
                ->count()
            + $this->registeredCustomers()
                ->where(['Users.created_at <' => $dayEnd])
                ->count();

        $customersSelectedDate =
            $this->squareCustomers()
                ->where([
                    'Customers.square_created_at >=' => $dayStart,
                    'Customers.square_created_at <' => $dayEnd,
                ])
                ->count()
            + $this->registeredCustomers()
                ->where([
                    'Users.created_at >=' => $dayStart,
                    'Users.created_at <' => $dayEnd,
                ])
                ->count();

        $recentCustomers = $this->recentCustomers($dayEnd, 10);

        // Send data to dashboard
        $this->set(compact(
            'identity',
            'selectedDate',
            'totalCustomers',
            'customersSelectedDate',
            'recentCustomers'
        ));

        return $this->render();
    }

    /**
     * Customers synced from Square.
     *
     * Soft-deleted rows are left out: those are people Square no longer
     * reports, so counting them would overstate the business's customers.
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
     * Accounts already linked to a Square customer are excluded, because
     * that same person is counted through their Customers row. Without this
     * the two sources would double-count everyone who both registered here
     * and booked through Square.
     *
     * Staff accounts are not customers, so only the `user` role counts.
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
     * The newest customers across both sources, as plain rows the template
     * can render without caring which table each came from.
     *
     * Pulling $limit from each source before merging is enough to be exact:
     * the true newest $limit of the combined set is always contained in the
     * newest $limit of each source.
     *
     * @param \Cake\I18n\FrozenTime $before Only customers created before this.
     * @param int $limit How many rows to return.
     * @return array<int, array<string, mixed>>
     */
    protected function recentCustomers(FrozenTime $before, int $limit): array
    {
        $rows = [];

        $squareRows = $this->squareCustomers()
            ->where(['Customers.square_created_at <' => $before])
            ->orderBy(['Customers.square_created_at' => 'DESC'])
            ->limit($limit)
            ->all();

        foreach ($squareRows as $customer) {
            $rows[] = [
                'name' => trim(
                    ($customer->given_name ?? '') . ' ' .
                    ($customer->family_name ?? '')
                ),
                'email' => $customer->email_address,
                'phone' => $customer->phone_number,
                'created' => $customer->square_created_at,
                'source' => 'square',
            ];
        }

        $accountRows = $this->registeredCustomers()
            ->where(['Users.created_at <' => $before])
            ->orderBy(['Users.created_at' => 'DESC'])
            ->limit($limit)
            ->all();

        foreach ($accountRows as $user) {
            $rows[] = [
                'name' => trim($user->first_name . ' ' . $user->surname),
                'email' => $user->email,
                'phone' => null,
                'created' => $user->created_at,
                'source' => 'account',
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['created'] <=> $a['created']);

        return array_slice($rows, 0, $limit);
    }
}
