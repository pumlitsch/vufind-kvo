<?php

namespace knav\ILS\Logic;

class Holds extends \VuFind\ILS\Logic\Holds
{
	protected function formatHoldings($holdings)
    {
        $retVal = [];
        // Handle purchase history alongside other textual fields
        $textFieldNames = $this->catalog->getHoldingsTextFieldNames();
        $textFieldNames[] = 'purchase_history';

        foreach ($holdings as $groupKey => $items) {
            $retVal[$groupKey] = [
                'items' => $items,
                'location' => isset($items[0]['location'])
                    ? $items[0]['location'] : '',
                'locationhref' => isset($items[0]['locationhref'])
                    ? $items[0]['locationhref'] : '',
                'locationText' => isset($items[0]['locationText'])
                    ? $items[0]['locationText'] : '',
            ];            
        }

        return $retVal;
    }

}

