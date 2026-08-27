<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\SiteContentsTable;
use Cake\Http\Response;

/**
 * Website Content Controller
 *
 * Lets staff edit the words on the public home page without touching a
 * template. The set of editable pieces is declared in SiteContentsTable, so
 * this controller never needs to know what they are.
 *
 * @property \App\Model\Table\SiteContentsTable $SiteContents
 */
class SiteContentsController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Shares the dashboard shell so the sidebar stays put.
        $this->viewBuilder()->setLayout('admin');
    }

    /**
     * The editor, and the target of its own form.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index()
    {
        $this->requireAdmin();

        if ($this->request->is(['post', 'put', 'patch'])) {
            if ($this->request->getData('reset') !== null) {
                $this->SiteContents->resetToDefaults();

                $this->Flash->success('The home page has been restored to its original wording.');

                return $this->redirect(['action' => 'index']);
            }

            $saved = $this->SiteContents->saveValues($this->request->getData());

            if ($saved > 0) {
                $this->Flash->success('The home page has been updated.');
            } else {
                $this->Flash->error('Nothing was saved. Please try again.');
            }

            return $this->redirect(['action' => 'index']);
        }

        $values = $this->SiteContents->values();

        // Grouped so the form reads in the order the page does.
        $groups = [];

        foreach (SiteContentsTable::FIELDS as $key => $field) {
            $groups[$field['group']][$key] = $field;
        }

        $this->set(compact('values', 'groups'));
    }
}
