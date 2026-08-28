<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\SiteContentsTable;
use App\Service\ImageUploadService;
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
                $this->resetEverything();

                $this->Flash->success('The home page has been restored to its original wording.');

                return $this->redirect(['action' => 'index']);
            }

            $this->saveLayout();

            $this->SiteContents->saveValues($this->request->getData());

            /*
             * The picture is handled after the text, so a rejected upload
             * still leaves the wording saved and only reports what failed.
             */
            $imageError = $this->handleHeroImage();

            if ($imageError !== null) {
                /*
                 * Keyed so the editor can show this beside the file field it
                 * is about, rather than as a page-level banner far from the
                 * control that caused it.
                 */
                $this->Flash->error($imageError, ['key' => 'heroImage']);
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
            (array)$this->request->getData('section_variant')
        );
    }

    /**
     * Applies a hero photo upload or removal.
     *
     * @return string|null An error to show, or null when nothing went wrong.
     */
    protected function handleHeroImage(): ?string
    {
        $uploader = new ImageUploadService();
        $current = (string)$this->SiteContents->values()['hero.image'];

        if ($this->request->getData('hero_image_remove')) {
            $uploader->delete($current);
            $this->SiteContents->setValue('hero.image', '');

            return null;
        }

        $file = $this->request->getUploadedFile('hero_image');

        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $result = $uploader->store($file, 'hero');

        if (!$result['ok']) {
            return $result['error'];
        }

        $this->SiteContents->setValue('hero.image', $result['filename']);

        // Only once the replacement is safely stored.
        $uploader->delete($current);

        return null;
    }

    /**
     * Clears saved copy and removes any uploaded picture with it.
     *
     * @return void
     */
    protected function resetEverything(): void
    {
        $uploader = new ImageUploadService();

        $uploader->delete((string)$this->SiteContents->values()['hero.image']);

        $this->SiteContents->resetToDefaults();
        $this->fetchTable('SiteSections')->resetToDefaults();
    }
}
