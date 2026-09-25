<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\PaymentsTable;
use Cake\Http\Response;
use Cake\I18n\FrozenTime;

class PaymentsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();

        // Payment data is financially sensitive - restrict beyond the
        // base logged-in access that Customers/Bookings allow.
        $this->requireAdmin();
    }

    public function index(): Response
    {
        $status = $this->request->getQuery('status');
        $startDate = $this->request->getQuery('start_date');
        $endDate = $this->request->getQuery('end_date');

        // Only accept statuses we actually know about - anything else is
        // ignored rather than passed through to the query.
        if ($status !== null && !array_key_exists($status, PaymentsTable::STATUSES)) {
            $status = null;
        }

        $query = $this->fetchTable('Payments')
            ->find()
            ->contain(['Customers']);

        if ($status !== null) {
            $query->where(['Payments.status' => $status]);
        }

        if ($startDate) {
            try {
                $query->where(['Payments.square_created_at >=' => new FrozenTime($startDate . ' 00:00:00')]);
            } catch (\Exception) {
                $startDate = null;
            }
        }

        if ($endDate) {
            try {
                $query->where(['Payments.square_created_at <' => (new FrozenTime($endDate . ' 00:00:00'))->addDays(1)]);
            } catch (\Exception) {
                $endDate = null;
            }
        }

        $payments = $query
            ->orderBy([
                'Payments.square_created_at' => 'DESC',
            ])
            ->all();

        $this->set(compact('payments', 'status', 'startDate', 'endDate'));
        $this->set('statuses', PaymentsTable::STATUSES);

        return $this->render();
    }
}
