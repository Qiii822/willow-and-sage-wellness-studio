<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\SiteContentsTable;
use App\Service\ImageUploadService;
use Cake\Event\EventInterface;

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
    /**
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
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
                $this->resetEverything();

                $this->Flash->success('The home page has been restored to its original wording.');

                return $this->redirect(['action' => 'index']);
            }

            $this->saveLayout();

            // Any field that fails its own validation (e.g. the booking
            // link) is left untouched rather than saved - everything else
            // submitted is still written.
            $valueErrors = $this->SiteContents->saveValues($this->request->getData());

            /*
             * Pictures are handled after the text, so a rejected upload still
             * leaves the wording saved and only reports what failed.
             */
            $imageErrors = $this->handleImages();

            if ($imageErrors !== []) {
                /*
                 * Keyed per field so the editor shows each one beside the file
                 * field it is about, rather than as a page-level banner far
                 * from the control that caused it.
                 */
                foreach ($imageErrors as $flashKey => $message) {
                    $this->Flash->error($message, ['key' => $flashKey]);
                }
            } elseif ($valueErrors !== []) {
                // Framework's default Flash element renders this as a single
                // escaped line, so errors are joined rather than newline-separated.
                $this->Flash->error(
                    "Some changes weren't saved - " . implode('; ', $valueErrors),
                );
            } else {
                $this->Flash->success('The home page has been updated.');
            }

            return $this->redirect(['action' => 'index']);
        }

        $values = $this->SiteContents->values();
        $sections = $this->fetchTable('SiteSections')->layout();

        // Grouped so the form reads in the order the page does.
        $groups = [];

        foreach (SiteContentsTable::FIELDS as $key => $field) {
            $groups[$field['group']][$key] = $field;
        }

        $this->set(compact('values', 'groups', 'sections'));
    }

    /**
     * Stores the block order, visibility and arrangement.
     *
     * The order is taken from the sequence `section_order` arrives in, which
     * the editor rewrites as rows are moved, so the saved order is whatever
     * was on screen.
     *
     * @return void
     */
    protected function saveLayout(): void
    {
        $order = (array)$this->request->getData('section_order');

        if ($order === []) {
            return;
        }

        $this->fetchTable('SiteSections')->saveLayout(
            $order,
            (array)$this->request->getData('section_visible'),
            (array)$this->request->getData('section_variant'),
        );
    }

    /**
     * Applies an upload or removal for every picture the editor declares.
     *
     * @return array<string, string> Flash key to error message, for the
     *  pictures that were rejected. Empty when nothing went wrong.
     */
    protected function handleImages(): array
    {
        $uploader = new ImageUploadService();
        $values = $this->SiteContents->values();
        $errors = [];

        foreach (SiteContentsTable::FIELDS as $key => $field) {
            if ($field['type'] !== SiteContentsTable::TYPE_IMAGE) {
                continue;
            }

            $input = SiteContentsTable::imageInputName($key);
            $current = (string)($values[$key] ?? '');

            if ($this->request->getData($input . '_remove')) {
                $uploader->delete($current);
                $this->SiteContents->setValue($key, '');

                continue;
            }

            $file = $this->request->getUploadedFile($input);

            if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            // Names the stored file after its field: hero-..., about-...
            $result = $uploader->store($file, explode('.', $key)[0]);

            if (!$result['ok']) {
                $errors[SiteContentsTable::imageFlashKey($key)] = $result['error'];

                continue;
            }

            $this->SiteContents->setValue($key, $result['filename']);

            // Only once the replacement is safely stored.
            $uploader->delete($current);
        }

        return $errors;
    }

    /**
     * Clears saved copy and removes any uploaded picture with it.
     *
     * @return void
     */
    protected function resetEverything(): void
    {
        $uploader = new ImageUploadService();
        $values = $this->SiteContents->values();

        // Every picture, not just the hero, or resetting would strand files.
        foreach (SiteContentsTable::FIELDS as $key => $field) {
            if ($field['type'] === SiteContentsTable::TYPE_IMAGE) {
                $uploader->delete((string)($values[$key] ?? ''));
            }
        }

        $this->SiteContents->resetToDefaults();
        $this->fetchTable('SiteSections')->resetToDefaults();
    }
}
