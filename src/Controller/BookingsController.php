<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\BookingsTable;
use Cake\Http\Response;
use Cake\I18n\FrozenTime;

class BookingsController extends AppController
{
    public function index(): Response
    {
        $status = $this->request->getQuery('status');
        $startDate = $this->request->getQuery('start_date');
        $endDate = $this->request->getQuery('end_date');

        // Only accept statuses we actually know about - anything else is
        // ignored rather than passed through to the query.
        if ($status !== null && !array_key_exists($status, BookingsTable::STATUSES)) {
            $status = null;
        }

        $query = $this->fetchTable('Bookings')
            ->find()
            ->contain(['Customers']);

        if ($status !== null) {
            $query->where(['Bookings.status' => $status]);
        }

        if ($startDate) {
            try {
                $query->where(['Bookings.start_at >=' => new FrozenTime($startDate . ' 00:00:00')]);
            } catch (\Exception) {
                $startDate = null;
            }
        }

        if ($endDate) {
            try {
                $query->where(['Bookings.start_at <' => (new FrozenTime($endDate . ' 00:00:00'))->addDays(1)]);
            } catch (\Exception) {
                $endDate = null;
            }
        }

        $bookings = $query
            ->orderBy([
                'start_at' => 'DESC',
            ])
            ->all();

        $this->set(compact('bookings', 'status', 'startDate', 'endDate'));
        $this->set('statuses', BookingsTable::STATUSES);

        return $this->render();
    }
}
