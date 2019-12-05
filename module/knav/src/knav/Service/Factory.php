<?php

namespace knav\Service;

class Factory
{

    /**
     * Construct the cache manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Cache\Manager
     */
    public static function getCacheManager(\Zend\ServiceManager\ServiceManager $sm)
    {
        return new \knav\Cache\Manager(
            $sm->get('VuFind\Config')->get('config'),
            $sm->get('VuFind\Config')->get('searches')
        );
    }

    /**
     * Construct the ILS hold logic.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\ILS\Logic\Holds
     */
    public static function getILSHoldLogic(\Zend\ServiceManager\ServiceManager $sm)
    {
        return new \knav\ILS\Logic\Holds(
            $sm->get('VuFind\ILSAuthenticator'), $sm->get('VuFind\ILSConnection'),
            $sm->get('VuFind\HMAC'), $sm->get('VuFind\Config')->get('config')
        );
    }

}

