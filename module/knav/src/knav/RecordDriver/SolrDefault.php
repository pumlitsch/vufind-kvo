<?php

namespace knav\RecordDriver;

class SolrDefault extends \VuFind\RecordDriver\SolrDefault
{
    public function getThumbnailKnav($size = 'small')
    {
        if (isset($this->fields['thumbnail']) && $this->fields['thumbnail']) {
            return $this->fields['thumbnail'];
        }
        $arr = [
            'author'     => mb_substr($this->getPrimaryAuthor(), 0, 300, 'utf-8'),
            'callnumber' => $this->getCallNumber(),
            'size'       => $size,
            'contenttype' => $this->fields['format'][0],
            'title'      => mb_substr($this->getTitle(), 0, 300, 'utf-8')
        ];
        if ($isbn = $this->getCleanISBN()) {
            $arr['isbn'] = $isbn;
        }
        if ($issn = $this->getCleanISSN()) {
            $arr['issn'] = $issn;
        }
        if ($oclc = $this->getCleanOCLCNum()) {
            $arr['oclc'] = $oclc;
        }
        if ($upc = $this->getCleanUPC()) {
            $arr['upc'] = $upc;
        }
        // If an ILS driver has injected extra details, check for IDs in there
        // to fill gaps:
        if ($ilsDetails = $this->getExtraDetail('ils_details')) {
            foreach (['isbn', 'issn', 'oclc', 'upc'] as $key) {
                if (!isset($arr[$key]) && isset($ilsDetails[$key])) {
                    $arr[$key] = $ilsDetails[$key];
                }
            }
        }
        return $arr;
    }

    public function getUnits()
    {
        return isset($this->fields['unit'])
            ? $this->fields['unit'] : [];
    }

    public function getCollection()
    {
        return isset($this->fields['collection'])
            ? $this->fields['collection'] : [];
    }

    public function getPublishPlace()
    {
         return isset($this->fields['publishPlace'])
            ? $this->fields['publishPlace'] : null;
    }

    public function getKVOPublisher()
    {
        return isset($this->fields['publisher']) ?
            trim($this->fields['publisher'][0], ",;:/") : null;
    }

    public function getBCBT()
    {
         return isset($this->fields['bcbt'])
            ? $this->fields['bcbt'] : null;
    }

    public function getMuaSig()
    {
         return isset($this->fields['mua_sig'])
            ? $this->fields['mua_sig'] : null;
    }

    public function getExpandedDate()
    {
        return isset($this->fields['publishDateExpanded']) ?
            rtrim($this->fields['publishDateExpanded'], '.') : null;
    }

    public function getRecordSource()
    {
         return isset($this->fields['record_source'])
            ? $this->fields['record_source'] : null;
    }

    /**
    *   najde k ilustraci hlavni/nadrazeny zaznam, podle bcbt
    */
    public function getParentRecord() {
      $result = array();

      if ($this->fields['record_source'] == "clavius") {
        if ($this->fields['bcbt']) {
          $pieces = explode('/', $this->fields['bcbt']);

          // jsme v podrizenym zaznamu - ma bcbt x/y
          if (!empty($pieces[1])) {

            $query = new \VuFindSearch\Query\Query('bcbt:' . $pieces[0]);
            $record = $this->searchService->search('Solr', $query, 0, 1)->first();

            if ($record) {
                $result['id'] = $record->getUniqueID();
                $result['title'] = $record->getShortTitle();
                $result['author'] = $record->getPrimaryAuthor();
                $result['bcbt'] = $record->getBCBT();
            }
          }
        }
      }

      return $result;
    }

    public function getTitleCaption()
    {
        return isset($this->fields['titleCaption']) ? $this->fields['titleCaption'] : '';
    }

    public function getTitlePageText()
    {
        return isset($this->fields['titlePageText']) ? $this->fields['titlePageText'] : [];
    }

    public function getImpresumText()
    {
        return isset($this->fields['impresumText']) ? $this->fields['impresumText'] : '';
    }

    public function getMasterPrinter()
    {
        return isset($this->fields['masterPrinter']) ? $this->fields['masterPrinter'] : '';
    }

    public function getPublishPerson()
    {
        return isset($this->fields['publishPerson']) ? $this->fields['publishPerson'] : '';
    }

    public function getContributor()
    {
        return isset($this->fields['contributor']) ? $this->fields['contributor'] : '';
    }

    public function getCompositor()
    {
        return isset($this->fields['compositor']) ? $this->fields['compositor'] : '';
    }

    public function getCensor()
    {
        return isset($this->fields['censor']) ? $this->fields['censor'] : '';
    }

    public function getDedicator()
    {
        return isset($this->fields['dedicator']) ? $this->fields['dedicator'] : '';
    }

    public function getDedicant()
    {
        return isset($this->fields['dedicant']) ? $this->fields['dedicant'] : '';
    }

    public function getEditor()
    {
        return isset($this->fields['editor']) ? $this->fields['editor'] : '';
    }

    public function getImaginaryAuthor()
    {
        return isset($this->fields['imaginaryAuthor']) ? $this->fields['imaginaryAuthor'] : '';
    }

    public function getTranslator()
    {
        return isset($this->fields['translator']) ? $this->fields['translator'] : '';
    }

    public function getForewordAuthor()
    {
        return isset($this->fields['forewordAuthor']) ? $this->fields['forewordAuthor'] : '';
    }

    public function getOriginatorPerson()
    {
        return isset($this->fields['originatorPerson']) ? $this->fields['originatorPerson'] : '';
    }

    public function getAncestor()
    {
        return isset($this->fields['ancestor']) ? $this->fields['ancestor'] : '';
    }

    public function getRecipient()
    {
        return isset($this->fields['recipient']) ? $this->fields['recipient'] : '';
    }

    public function getAdditionalAuthor()
    {
        return isset($this->fields['author_additional']) ? $this->fields['author_additional'] : '';
    }

    public function getOtherAuthor()
    {
        return isset($this->fields['otherAuthor']) ? $this->fields['otherAuthor'] : '';
    }

    public function getAuthors()
    {
        return isset($this->fields['author']) ? $this->fields['author'] : '';
    }

    public function getAuthHolder()
    {
        return isset($this->fields['authHolder']) ? $this->fields['authHolder'] : '';
    }

    public function getAuthorPersonal()
    {
//       dump($this->fields['authorPersonal']);
        return isset($this->fields['authorPersonal']) ? $this->fields['authorPersonal'] : '';
    }

    public function getLiterature()
    {
        return isset($this->fields['literature']) ? $this->fields['literature'] : [];
    }

    public function getSubjects() {
        return isset($this->fields['subject']) ? $this->fields['subject'] : '';
    }

    public function getReferences() {
        return isset($this->fields['references']) ? $this->fields['references'] : '';
    }

    public function getNos()
    {
        return isset($this->fields['nos']) ? $this->fields['nos'] : '';
    }

    public function getTopics() {
        return isset($this->fields['topic']) ? $this->fields['topic'] : '';
    }

    public function getGeographic() {
        return isset($this->fields['geographic']) ? $this->fields['geographic'] : '';
    }

    public function getGeoCoordinates() {
        return isset($this->fields['geo']) ? $this->fields['geo'] : '';
    }

    public function getLongLatFacet() {
        return isset($this->fields['long_lat_facet']) ? $this->fields['long_lat_facet'] : '';
    }

    public function getPublishPlaceGeo() {
        return isset($this->fields['publishPlace_geo']) ? $this->fields['publishPlace_geo'] : '';
    }

    public function getEra() {
        return isset($this->fields['era']) ? $this->fields['era'] : '';
    }

    public function getGenre() {
        return isset($this->fields['genre']) ? $this->fields['genre'] : '';
    }

    public function getEdition()
    {
        return isset($this->fields['edition']) ? $this->fields['edition'] : '';
    }

    public function getCatalogLink() {
      if (($this->fields['record_source'] == 'clavius') || ($this->fields['record_source'] == 'mua') || ($this->fields['record_source'] == 'mua-kod')) {
        return $this->getClaviusLink();
      } else if ($this->fields['record_source'] == 'nkp'){
        return $this->getNkpLink();
      } else {
        return null;
      }
    }

    public function getClaviusLink()
    {
        $claviusId = substr($this->getUniqueID(), -6);

        if ($this->fields['record_source'] == 'clavius') {
        return $this->mainConfig->Links->clavius_catalog . $this->mainConfig->Links->clavius_record . $claviusId;
        } else if (($this->fields['record_source'] == 'mua') || ($this->fields['record_source'] == 'mua-kod')){
        return $this->mainConfig->Links->tslanius_catalog .  $this->mainConfig->Links->tslanius_record . $claviusId;
        } else {
        return null;
        }
    }

    public function getContents() {
        return isset($this->fields['contents']) ? $this->fields['contents'] : '';
    }


    function illuSort($a, $b) {
        if ($a['bcbt'] == $b['bcbt']) {
            return 0;
        }
        return ($a['bcbt'] < $b['bcbt']) ? -1 : 1;
    }

    /**
    *   přiřadí k záznamu související ilustrace, podle stejného začátku bcbt
    */
    public function getIllustrations() {
      $result = array();

      if ($this->fields['record_source'] == "clavius") {
        if ($this->fields['bcbt']) {

            $query = new \VuFindSearch\Query\Query('bcbt:' . trim($this->fields['bcbt'], "\/") . '/*');
            $records = $this->searchService->search('Solr', $query, 0, 60)->getRecords();

            foreach ($records as $key => $value) {
                $result[] = ['id' => $value->getUniqueID(), 'title' => $value->getShortTitle(),
                            'author' => $value->getPrimaryAuthor(), 'bcbt' => $value->getBCBT()];
             }
          }
        }

      uasort($result, array($this, "illuSort"));

      return $result;
    }

    public function getImprint() {
        return isset($this->fields['imprint']) ? $this->fields['imprint'] : '';
    }

    public function getRecordNumber() {
        if ($this->fields['record_source'] == 'clavius') {
            return $this->fields['bcbt'];
        } else if ($this->fields['record_source'] == 'nkp') {
            return $this->fields['id'];
        } else if ($this->fields['record_source'] == 'mua') {
            return $this->fields['id'];
        }

    }

    public function getIndexedUrls() {
        return isset($this->fields['url']) ? $this->fields['url'] : '';
    }

    public function getAuthorEncyclopedyLink($author)
    {
      $links = $this->mainConfig->Links->authorlinks->toArray();
      if (!empty($links[$author])) {
	return $links[$author];
      } else {
	return null;
      }
    }
}
