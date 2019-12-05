<?php

namespace knav\View\Helper\Root;
use Zend\View\Exception\RuntimeException, Zend\View\Helper\AbstractHelper;

class Record extends \VuFind\View\Helper\Root\Record
{

    public function getTitleHtml($maxLength = 180)
    {
        $highlightedTitle = $this->driver->tryMethod('getHighlightedTitle');
        $title = trim($this->driver->tryMethod('getTitle'));
        if (!empty($highlightedTitle)) {
            $highlight = $this->getView()->plugin('highlight');
            $addEllipsis = $this->getView()->plugin('addEllipsis');
            $result = $highlight($addEllipsis($highlightedTitle, $title));
            $result = trim($result, " /");
            return $result;
        }
        if (!empty($title)) {
            $escapeHtml = $this->getView()->plugin('escapeHtml');
            $truncate = $this->getView()->plugin('truncate');
            $result = $escapeHtml($truncate($title, $maxLength));
            $result = trim($result, " /");
            return $result;
        }
        $transEsc = $this->getView()->plugin('transEsc');
        return $transEsc('Title not available');
    }


}
