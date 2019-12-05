<?php

namespace knav\Controller;

class SolrRedirectController extends \VuFind\Controller\AbstractBase
{

    public function biblioAction()
    {
        $this->layout()->setTemplate('solrredirect/biblio/select');
        return $this->createViewModel(['biblio' => "biblio", 'select' => 'select']);
    }
}
