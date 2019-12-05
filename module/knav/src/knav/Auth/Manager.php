<?php

namespace knav\Auth;
use VuFind\Db\Table\User as UserTable,
    Zend\Validator\Csrf;


/**
 * Wrapper class for handling logged-in user in session.
*/
class Manager extends \VuFind\Auth\Manager
{
   
    public function __construct(\Zend\Config\Config $config, UserTable $userTable,
        \Zend\Session\SessionManager $sessionManager, \VuFind\Auth\PluginManager $pm,
        \VuFind\Cookie\CookieManager $cookieManager
    ) {
        // Store dependencies:
        $this->config = $config;
        $this->userTable = $userTable;
        $this->sessionManager = $sessionManager;
        $this->pluginManager = $pm;
        $this->cookieManager = $cookieManager;

        $this->session = new \Zend\Session\Container('Account', $sessionManager);

        $this->csrf = new Csrf(
            [
                'session' => new \Zend\Session\Container('csrf', $sessionManager),
                'salt' => isset($this->config->Security->HMACkey)
                    ? $this->config->Security->HMACkey : 'VuFindCsrfSalt',
            ]
        );

        // Initialize active authentication setting (defaulting to Database
        // if no setting passed in):
        $method = isset($config->Authentication->method)
            ? $config->Authentication->method : 'Database';     

        $this->legalAuthOptions = [$method];   
        $this->setAuthMethod($method);             
    }

 
}
