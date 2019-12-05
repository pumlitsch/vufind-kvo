<?php

namespace knav\ILS\Driver;

class Factory
{

    /**
     * Factory for Aleph driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Aleph
     */
    public static function getAleph(\Zend\ServiceManager\ServiceManager $sm)
    {
        return new \knav\ILS\Driver\Aleph(
            $sm->getServiceLocator()->get('VuFind\DateConverter'),
            $sm->getServiceLocator()->get('VuFind\CacheManager')
        );
    }


}

