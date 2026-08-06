<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;
use App\Service\SquareAuthService;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authentication.Authentication');

        $this->loadComponent('Flash');

        
        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
        

        // Square OAuth requirement
        // Requires the user be authenticated to access any page of the website
        // Redirects to /auth if not
        // $this->middleware(function ($request, $handler) {
        //     // Allow auth controller actions without authentication
        //     $allowedActions = ['Auth', 'Auth.index', 'Auth.login', 'Auth.callback', 'Auth.logout', 'Auth.success', 'Users.login'];
        //     $currentAction = $request->getParam('controller') . '.' . $request->getParam('action');

        //     if (in_array($currentAction, $allowedActions)) {
        //         return $handler->handle($request);
        //     }

        //     $session = $request->getSession();

        //     // Read session_id from the cookie (source of truth), not PHP session
        //     $sessionId = $request->getCookie('session_id');

        //     $squareAuth = new SquareAuthService();
        //     $sessionRecord = $squareAuth->validateSession($sessionId);

        //     if ($sessionRecord) {
        //         // Rehydrate PHP session for convenience this request cycle
        //         $session->write('identity_id', $sessionRecord->identity_id);
        //         $session->write('merchant_id', $sessionRecord->identity->merchant_id);
        //         $session->write('session_id', $sessionRecord->id);

        //         return $handler->handle($request);
        //     }

        //     // Not logged in (or session's dead), remember where they were headed
        //     // so we can send them back after they log in
        //     $session->write('redirect_after_auth', $request->getRequestTarget());
        //     return $this->getResponse()
        //         ->withStatus(302)
        //         ->withLocation('/auth');
        // });

    }

    protected function requireAdmin(): void
    {
        $user = $this->request->getAttribute('identity');

        if (!$user || $user->roles !== 'admin') {
            throw new \Cake\Http\Exception\ForbiddenException('Admin access only.');
        }
    }
 
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        // for all controllers in our application, make index and view
        // actions public, skipping the authentication check
        // $this->Authentication->allowUnauthenticated(['index', 'view']);
    } 
}
