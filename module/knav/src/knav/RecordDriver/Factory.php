<?php

namespace knav\RecordDriver;
use Zend\ServiceManager\ServiceManager;

class Factory
{
    public static function getSolrDefault(ServiceManager $sm)
    {
        $driver = new \knav\RecordDriver\SolrDefault(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachSearchService($sm->getServiceLocator()->get('VuFind\Search'));
        return $driver;
    }

    public static function getSolrMarc(ServiceManager $sm)
    {
        $driver = new \knav\RecordDriver\SolrMarc(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
            null,
            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
        );
        $driver->attachSearchService($sm->getServiceLocator()->get('VuFind\Search'));
        return $driver;
    }


}

