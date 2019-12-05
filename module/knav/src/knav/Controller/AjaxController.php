<?php

namespace knav\Controller;

class AjaxController extends \VuFind\Controller\AjaxController
{

	/**
     * Get Data for GPE payment
     *
     *
     * @return array
     * @access public
     */
    protected function getGpeDataAjax() {

        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $digest = null;

		$api_key = openssl_pkey_get_private($patron['key']);

        $gpeData = array(
	    	'TIME' => time(),
            'ID' => $patron['id'],
            'ADM' => 'KNA50',
            'AMOUNT' => $_POST['amount'],
            );

        $signed_params = implode('|', $gpeData);


        if (openssl_sign($signed_params, $digest, $api_key)) {
            $gpeData['DIGEST'] = base64_encode($digest);
        } else {
            openssl_free_key($api_key);
            return $this->output("Failure creating openssl signature.", self::STATUS_ERROR);
        }

        openssl_free_key($api_key);

        return $this->output($gpeData, self::STATUS_OK);
    }


    /**
     * Get Status Code
     *
     *
     * @return void
     * @access public
     */
    protected function getHistoricalStatusAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $catalog = $this->getILS();
        $id = $_GET['id'][0];
        $results = $catalog->getHolding($id);

        if (!is_array($results)) {
            $results = [];
        }

        $renderer = $this->getViewRenderer();

        // Load messages for response:
        $messages = [
            '0' => $renderer->render('ajax/historical-false.phtml'),
            '1' => $renderer->render('ajax/historical-true.phtml', ['id' => $id]),
        ];

        $historical_status = FALSE;

        foreach ($results as $record) {
                if (empty($record)) {
                    continue;
                }

                if ($record['historical_collection']) {
                    $historical_status = TRUE;
                }
        }

        $return_status = array( 'historical' => $historical_status, 'resu' => $results, 'message' => $messages[$historical_status]);

        return $this->output($return_status, self::STATUS_OK);
    }

}

