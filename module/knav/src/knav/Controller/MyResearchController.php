<?php

namespace knav\Controller;

class MyResearchController extends \VuFind\Controller\MyResearchController
{

	public function historyAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $catalog = $this->getILS();
        $result = $catalog->getMyTransactions($patron, true);

        $config = $this->getConfig();
        $limit = isset($config->Catalog->checked_out_page_size)
            ? $config->Catalog->checked_out_page_size : 50;

        // Build paginator if needed:
        if ($limit > 0 && $limit < count($result)) {
            $adapter = new \Zend\Paginator\Adapter\ArrayAdapter($result);
            $paginator = new \Zend\Paginator\Paginator($adapter);
            $paginator->setItemCountPerPage($limit);
            $paginator->setCurrentPageNumber($this->params()->fromQuery('page', 1));
            $pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
            $pageEnd = $paginator->getAbsoluteItemNumber($limit) - 1;
        } else {
            $paginator = false;
            $pageStart = 0;
            $pageEnd = count($result);
        }

        $transactions = $hiddenTransactions = [];
        foreach ($result as $i => $current) {

            if ($i >= $pageStart && $i <= $pageEnd) {
                $transactions[] = $this->getDriverForILSRecord($current);
            } else {
                $hiddenTransactions[] = $current;
            }
        }

        return $this->createViewModel(
            compact(
                'transactions', 'paginator',
                'hiddenTransactions'
            )
        );
    }

    public function finesAction()
    {
    	$config = $this->getConfig();
    	$payurl = $config->Knav->pay_url;

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $catalog = $this->getILS();

        $result = $catalog->getMyFines($patron);
        $fines = [];
        foreach ($result as $type => $subarray) {
        	foreach ($subarray as $row) {
	            // Attempt to look up and inject title:
	            try {
	                if (!isset($row['id']) || empty($row['id'])) {
	                    throw new \Exception();
	                }
	                $source = isset($row['source'])
	                    ? $row['source'] : DEFAULT_SEARCH_BACKEND;
	                $row['driver'] = $this->getServiceLocator()
	                    ->get('VuFind\RecordLoader')->load($row['id'], $source);
	                $row['title'] = $row['driver']->getShortTitle();
	            } catch (\Exception $e) {
	                if (!isset($row['title'])) {
	                    $row['title'] = null;
	                }
	            }
	            $fines[$type][] = $row;
        	}
    	}

        return $this->createViewModel(['fines' => $fines, 'patron' => $patron, 'payurl' => $payurl]);
    }

    /**
     * Send list of studyrooms to view
     *
     * @return mixed
     */
    public function studyroomsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $catalog = $this->getILS();
        $view = $this->createViewModel();       

        // By default, assume we will not need to display a cancel form:
        $view->cancelForm = false;

        $result = $catalog->getMyHolds($patron);
        $recordList = [];
        $this->holds()->resetValidation();
        foreach ($result as $current) {
            $recordList[] = $this->getDriverForILSRecord($current);
        }

        // Get List of PickUp Libraries based on patron's home library
        try {
            $view->pickup = $catalog->getPickUpLocations($patron);
        } catch (\Exception $e) {
            // Do nothing; if we're unable to load information about pickup
            // locations, they are not supported and we should ignore them.
        }
        $view->recordList = $recordList;
        return $view;
    }


    public function cancelRoomAction()
    {
         $view = $this->createViewModel();   
        
        return $view;
    }

}

