<?php

namespace knav\View\Helper\Root;
use Zend\ServiceManager\ServiceManager;

class Factory
{

    /**
     * Construct the Citation helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Citation
     */
    public static function getCitation(ServiceManager $sm)
    {
        return new \knav\View\Helper\Root\Citation($sm->getServiceLocator()->get('VuFind\DateConverter'));
    }

    public static function getRecord(ServiceManager $sm)
    {
        return new \knav\View\Helper\Root\Record(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

}
