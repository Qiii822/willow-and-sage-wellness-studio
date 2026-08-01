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

        $this->loadComponent('Flash');

        
        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');

        // Square OAuth requirement
        //Requires the user be authenticated to access any page of the website
        //Redirects to /auth if not
        $this->middleware(function ($request, $handler) {
        // Allow auth controller actions without authentication
        $allowedActions = ['Auth', 'Auth.index', 'Auth.login', 'Auth.callback'];
        $currentAction = $request->getParam('controller') . '.' . $request->getParam('action');
        
        if (in_array($currentAction, $allowedActions)) {
            return $handler->handle($request);
        }
        
        // Check if user is authenticated via Square
        $session = $request->getSession();
        $accessToken = $session->read('square_access_token');
        $merchantId = $session->read('square_token_payload.merchant_id');
        $authorizedMerchantId = env('SQUARE_AUTHORIZED_MERCHANT_ID');
        
        if (!$accessToken || $merchantId !== $authorizedMerchantId) {
            // Not authenticated or wrong merchant
            return $this->getResponse()
                ->withStatus(302)
                ->withLocation('/auth');
        }
        
        return $handler->handle($request);
        });

    }
}
