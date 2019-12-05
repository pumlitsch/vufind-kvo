<?php
/**
 * Model for MARC records in Solr.
 */
namespace knav\RecordDriver;
use VuFind\Exception\ILS as ILSException,
    VuFind\View\Helper\Root\RecordLink,
    VuFind\XSLT\Processor as XSLTProcessor;

/**
 * Model for MARC records in Solr.
 */


class SolrMarc extends \knav\RecordDriver\SolrDefault
{

    /**
     * MARC record. Access only via getMarcRecord() as this is initialized lazily.
     *
     * @var \File_MARC_Record
     */
    protected $lazyMarcRecord = null;

    /**
     * ILS connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $ils = null;

    /**
     * Hold logic
     *
     * @var \VuFind\ILS\Logic\Holds
     */
    protected $holdLogic;

    /**
     * Title hold logic
     *
     * @var \VuFind\ILS\Logic\TitleHolds
     */
    protected $titleHoldLogic;

    /**
     * Get access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        return $this->getFieldArray('506');
    }

    /**
     * Get all subject headings associated with this record.  Each heading is
     * returned as an array of chunks, increasing from least specific to most
     * specific.
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        // These are the fields that may contain subject headings:
        $fields = [
            '600', '610', '611', '630', '648', '650', '651', '653', '655', '656'
        ];

        // This is all the collected data:
        $retval = [];

        foreach ($fields as $field) {
            $results = $this->getMarcRecord()->getFields($field);
            if (!$results) {
                continue;
            }

            foreach ($results as $result) {
                $current = [];

                $subfields = $result->getSubfields();
                if ($subfields) {
                    foreach ($subfields as $subfield) {
                        if (!is_numeric($subfield->getCode())) {
                            $current[] = $subfield->getData();
                        }
                    }
                    if (!empty($current)) {
                        $retval[] = $current;
                    }
                }
            }
        }

        // Remove duplicates and then send back everything we collected:
        return array_map(
            'unserialize', array_unique(array_map('serialize', $retval))
        );
    }

    /**
     * Get award notes for the record.
     *
     * @return array
     */
    public function getAwards()
    {
        return $this->getFieldArray('586');
    }

    /**
     * Get the bibliographic level of the current record.
     *
     * @return string
     */
    public function getBibliographicLevel()
    {
        $leader = $this->getMarcRecord()->getLeader();
        $biblioLevel = strtoupper($leader[7]);

        switch ($biblioLevel) {
        case 'M': 
            return "Monograph";
        case 'S': 
            return "Serial";
        case 'A': 
            return "MonographPart";
        case 'B': 
            return "SerialPart";
        case 'C': 
            return "Collection";
        case 'D': 
            return "CollectionPart";
        default:
            return "Unknown";
        }
    }

    /**
     * Get notes on bibliography content.
     *
     * @return array
     */
    public function getBibliographyNotes()
    {
        return $this->getFieldArray('504');
    }

    /**
     * Return an array of all values extracted from the specified field/subfield combination. 
     *
     * @param string $field     The MARC field number to read
     * @param array  $subfields The MARC subfield codes to read
     * @param bool   $concat    Should we concatenate subfields?
     * @param string $separator Separator string (used only when $concat === true)
     *
     * @return array
     */
    protected function getFieldArray($field, $subfields = null, $concat = true,
        $separator = ' '
    ) {
        // Default to subfield a if nothing is specified.
        if (!is_array($subfields)) {
            $subfields = ['a'];
        }

        $matches = [];
        $fields = $this->getMarcRecord()->getFields($field);
        if (!is_array($fields)) {
            return $matches;
        }

        foreach ($fields as $currentField) {
            $next = $this
                ->getSubfieldArray($currentField, $subfields, $concat, $separator);
            $matches = array_merge($matches, $next);
        }

        return $matches;
    }

    /**
     * Get notes on finding aids related to the record.
     *
     * @return array
     */
    public function getFindingAids()
    {
        return $this->getFieldArray('555');
    }

    /**
     * Get the first value matching the specified MARC field and subfields.
     * If multiple subfields are specified, they will be concatenated together.
     *
     * @param string $field     The MARC field to read
     * @param array  $subfields The MARC subfield codes to read
     *
     * @return string
     */
    protected function getFirstFieldValue($field, $subfields = null)
    {
        $matches = $this->getFieldArray($field, $subfields);
        return (is_array($matches) && count($matches) > 0) ?
            $matches[0] : null;
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        return $this->getFieldArray('500');
    }

    /**
     * Get human readable publication dates for display purposes
     *
     * @return array
     */
    public function getHumanReadablePublicationDates()
    {
        return $this->getPublicationInfo('c');
    }

    /**
     * Get an array of newer titles for the record.
     *
     * @return array
     */
    public function getNewerTitles()
    {
        $fieldsNames = isset($this->mainConfig->Record->marc_links)
            ? array_map('trim', explode(',', $this->mainConfig->Record->marc_links))
            : [];
        return in_array('785', $fieldsNames) ? [] : parent::getNewerTitles();
    }

    /**
     * Get the item's publication information
     *
     * @param string $subfield The subfield to retrieve ('a' = location, 'c' = date)
     *
     * @return array
     */
    protected function getPublicationInfo($subfield = 'a')
    {
        $separator = isset($this->mainConfig->Record->marcPublicationInfoSeparator)
            ? $this->mainConfig->Record->marcPublicationInfoSeparator : ' ';

        $results = $this->getFieldArray('260', [$subfield], true, $separator);

        $pubResults = $copyResults = [];

        $fields = $this->getMarcRecord()->getFields('264');
        if (is_array($fields)) {
            foreach ($fields as $currentField) {
                $currentVal = $this
                    ->getSubfieldArray($currentField, [$subfield], true, $separator);
                if (!empty($currentVal)) {
                    switch ($currentField->getIndicator('2')) {
                    case '1':
                        $pubResults = array_merge($pubResults, $currentVal);
                        break;
                    case '4':
                        $copyResults = array_merge($copyResults, $currentVal);
                        break;
                    }
                }
            }
        }
        $replace260 = isset($this->mainConfig->Record->replaceMarc260)
            ? $this->mainConfig->Record->replaceMarc260 : false;
        if (count($pubResults) > 0) {
            return $replace260 ? $pubResults : array_merge($results, $pubResults);
        } else if (count($copyResults) > 0) {
            return $replace260 ? $copyResults : array_merge($results, $copyResults);
        }

        return $results;
    }

    /**
     * Get the item's places of publication.
     *
     * @return array
     */
    public function getPlacesOfPublication()
    {
        return $this->getPublicationInfo();
    }

    
    /**
     * Get an array of previous titles for the record.
     *
     * @return array
     */
    public function getPreviousTitles()
    {
            $fieldsNames = isset($this->mainConfig->Record->marc_links)
            ? array_map('trim', explode(',', $this->mainConfig->Record->marc_links))
            : [];
        return in_array('780', $fieldsNames) ? [] : parent::getPreviousTitles();
    }

    /**
     * Get credits of people involved in production of the item.
     *
     * @return array
     */
    public function getProductionCredits()
    {
        return $this->getFieldArray('508');
    }

    /**
     * Get an array of publication frequency information.
     *
     * @return array
     */
    public function getPublicationFrequency()
    {
        return $this->getFieldArray('310', ['a', 'b']);
    }

    /**
     * Get an array of strings describing relationships to other items.
     *
     * @return array
     */
    public function getRelationshipNotes()
    {
        return $this->getFieldArray('580');
    }

    /**
     * Get an array of all series names containing the record.
     *
     * @return array
     */
    public function getSeries()
    {
        $matches = [];

        // First check the 440, 800 and 830 fields for series information:
        $primaryFields = [
            '440' => ['a', 'p'],
            '800' => ['a', 'b', 'c', 'd', 'f', 'p', 'q', 't'],
            '830' => ['a', 'p']];
        $matches = $this->getSeriesFromMARC($primaryFields);
        if (!empty($matches)) {
            return $matches;
        }

        // Now check 490 and display it only if 440/800/830 were empty:
        $secondaryFields = ['490' => ['a']];
        $matches = $this->getSeriesFromMARC($secondaryFields);
        if (!empty($matches)) {
            return $matches;
        }

        return parent::getSeries();
    }

    /**
     * Support method for getSeries() -- given a field specification, look for
     * series information in the MARC record.
     *
     * @param array $fieldInfo Associative array of field => subfield information
     * (used to find series name)
     *
     * @return array
     */
    protected function getSeriesFromMARC($fieldInfo)
    {
        $matches = [];

        foreach ($fieldInfo as $field => $subfields) {
            $series = $this->getMarcRecord()->getFields($field);
            if (is_array($series)) {
                foreach ($series as $currentField) {
                    $name = $this->getSubfieldArray($currentField, $subfields);
                    if (isset($name[0])) {
                        $currentArray = ['name' => $name[0]];
                        $number
                            = $this->getSubfieldArray($currentField, ['v']);
                        if (isset($number[0])) {
                            $currentArray['number'] = $number[0];
                        }

                        $matches[] = $currentArray;
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * Return an array of non-empty subfield values found in the provided MARC field.  
     *
     * @param object $currentField Result from File_MARC::getFields.
     * @param array  $subfields    The MARC subfield codes to read
     * @param bool   $concat       Should we concatenate subfields?
     * @param string $separator    Separator string
     *
     * @return array
     */
    protected function getSubfieldArray($currentField, $subfields, $concat = true,
        $separator = ' '
    ) {
        $matches = [];

        $allSubfields = $currentField->getSubfields();
        if (!empty($allSubfields)) {
            foreach ($allSubfields as $currentSubfield) {
                if (in_array($currentSubfield->getCode(), $subfields)) {
                    $data = trim($currentSubfield->getData());
                    if (!empty($data)) {
                        $matches[] = $data;
                    }
                }
            }
        }

        return $concat && $matches ? [implode($separator, $matches)] : $matches;
    }

    /**
     * Get an array of summary strings for the record.
     *
     * @return array
     */
    public function getSummary()
    {
        return $this->getFieldArray('520');
    }

    /**
     * Get an array of technical details on the item represented by the record.
     *
     * @return array
     */
    public function getSystemDetails()
    {
        return $this->getFieldArray('538');
    }

    /**
     * Get an array of note about the record's target audience.
     *
     * @return array
     */
    public function getTargetAudienceNotes()
    {
        return $this->getFieldArray('521');
    }

    /**
     * Get the text of the part/section portion of the title.
     *
     * @return string
     */
    public function getTitleSection()
    {
        return $this->getFirstFieldValue('245', ['n', 'p']);
    }

    /**
     * Get the statement of responsibility that goes with the title 
     *
     * @return string
     */
    public function getTitleStatement()
    {
        return $this->getFirstFieldValue('245', ['c']);
    }

    /**
     * Get an array of lines from the table of contents.
     *
     * @return array
     */
    public function getTOC()
    {
        $fields = $this->getMarcRecord()->getFields('505');
        if (!$fields) {
            return [];
        }

        $toc = [];
        foreach ($fields as $field) {
            $subfields = $field->getSubfields();
            foreach ($subfields as $subfield) {
                $toc = array_merge(
                    $toc,
                    array_filter(explode('--', $subfield->getData()), 'trim')
                );
            }
        }
        return $toc;
    }

    /**
     * Get hierarchical place names (MARC field 752)
     *
     * Returns an array of formatted hierarchical place names, consisting of all
     * alpha-subfields, concatenated for display
     *
     * @return array
     */
    public function getHierarchicalPlaceNames()
    {
        $placeNames = [];
        if ($fields = $this->getMarcRecord()->getFields('752')) {
            foreach ($fields as $field) {
                $subfields = $field->getSubfields();
                $current = [];
                foreach ($subfields as $subfield) {
                    if (!is_numeric($subfield->getCode())) {
                        $current[] = $subfield->getData();
                    }
                }
                $placeNames[] = implode(' -- ', $current);
            }
        }
        return $placeNames;
    }


    /**
     * Get all record links related to the current record. Each link is returned as
     * array.
     * @return null|array
     */
    public function getAllRecordLinks()
    {
        // Load configurations:
        $fieldsNames = isset($this->mainConfig->Record->marc_links)
            ? explode(',', $this->mainConfig->Record->marc_links) : [];
        $useVisibilityIndicator
            = isset($this->mainConfig->Record->marc_links_use_visibility_indicator)
            ? $this->mainConfig->Record->marc_links_use_visibility_indicator : true;

        $retVal = [];
        foreach ($fieldsNames as $value) {
            $value = trim($value);
            $fields = $this->getMarcRecord()->getFields($value);
            if (!empty($fields)) {
                foreach ($fields as $field) {
                    if ($useVisibilityIndicator) {
                        $visibilityIndicator = $field->getIndicator('1');
                        if ($visibilityIndicator == '1') {
                            continue;
                        }
                    }

                    $tmp = $this->getFieldData($field);
                    if (is_array($tmp)) {
                        $retVal[] = $tmp;
                    }
                }
            }
        }
        return empty($retVal) ? null : $retVal;
    }

    /**
     * Support method for getFieldData() -- factor the relationship indicator
     * into the field number where relevant to generate a note to associate
     * with a record link.
     *
     * @param File_MARC_Data_Field $field Field to examine
     *
     * @return string
     */
    protected function getRecordLinkNote($field)
    {
        if ($subfieldI = $field->getSubfield('i')) {
            $data = trim($subfieldI->getData());
            if (!empty($data)) {
                return $data;
            }
        }

        $relationshipIndicator = $field->getIndicator('2');
        if ($relationshipIndicator == ' ') {
            $relationshipIndicator = '0';
        }

        $value = $field->getTag();
        switch ($value) {
        case '780':
            if (in_array($relationshipIndicator, range('0', '7'))) {
                $value .= '_' . $relationshipIndicator;
            }
            break;
        case '785':
            if (in_array($relationshipIndicator, range('0', '8'))) {
                $value .= '_' . $relationshipIndicator;
            }
            break;
        }

        return 'note_' . $value;
    }

    /**
     * Returns the array element for the 'getAllRecordLinks' method
     *
     * @param File_MARC_Data_Field $field Field to examine
     *
     * @return array|bool                 Array on success, boolean false if no
     * valid link could be found in the data.
     */
    protected function getFieldData($field)
    {
        if ($title = $field->getSubfield('t')) {
            $title = $title->getData();
        } else {
            return false;
        }

        $linkTypeSetting = isset($this->mainConfig->Record->marc_links_link_types)
            ? $this->mainConfig->Record->marc_links_link_types
            : 'id,oclc,dlc,isbn,issn,title';
        $linkTypes = explode(',', $linkTypeSetting);
        $linkFields = $field->getSubfields('w');

        foreach ($linkTypes as $linkType) {
            switch (trim($linkType)){
            case 'oclc':
                foreach ($linkFields as $current) {
                    if ($oclc = $this->getIdFromLinkingField($current, 'OCoLC')) {
                        $link = ['type' => 'oclc', 'value' => $oclc];
                    }
                }
                break;
            case 'dlc':
                foreach ($linkFields as $current) {
                    if ($dlc = $this->getIdFromLinkingField($current, 'DLC', true)) {
                        $link = ['type' => 'dlc', 'value' => $dlc];
                    }
                }
                break;
            case 'id':
                foreach ($linkFields as $current) {
                    if ($bibLink = $this->getIdFromLinkingField($current)) {
                        $link = ['type' => 'bib', 'value' => $bibLink];
                    }
                }
                break;
            case 'isbn':
                if ($isbn = $field->getSubfield('z')) {
                    $link = [
                        'type' => 'isn', 'value' => trim($isbn->getData()),
                        'exclude' => $this->getUniqueId()
                    ];
                }
                break;
            case 'issn':
                if ($issn = $field->getSubfield('x')) {
                    $link = [
                        'type' => 'isn', 'value' => trim($issn->getData()),
                        'exclude' => $this->getUniqueId()
                    ];
                }
                break;
            case 'title':
                $link = ['type' => 'title', 'value' => $title];
                break;
            }
            if (isset($link)) {
                break;
            }
        }
        return !isset($link) ? false : [
            'title' => $this->getRecordLinkNote($field),
            'value' => $title,
            'link'  => $link
        ];
    }

    /**
     * Returns an id extracted from the identifier subfield passed in
     *
     * @param \File_MARC_Subfield $idField MARC field containing id information
     * @param string              $prefix  Prefix to search for in id field
     * @param bool                $raw     Return raw match, or normalize?
     *
     * @return string|bool                 ID on success, false on failure
     */
    protected function getIdFromLinkingField($idField, $prefix = null, $raw = false)
    {
        $text = $idField->getData();
        if (preg_match('/\(([^)]+)\)(.+)/', $text, $matches)) {
            if ($matches[1] == $prefix) {
                // Special case -- LCCN should not be stripped:
                return $raw
                    ? $matches[2]
                    : trim(str_replace(range('a', 'z'), '', ($matches[2])));
            }
        } else if ($prefix == null) {
            // If no prefix was given or found, we presume it is a raw bib record
            return $text;
        }
        return false;
    }

    /**
     * Get Status/Holdings Information from the internally stored MARC Record
     *
     * @param array $field The MARC Field to retrieve
     * @param array $data  A keyed array of data to retrieve from subfields
     *
     * @return array
     */
    public function getFormattedMarcDetails($field, $data)
    {
        $matches = [];
        $i = 0;

        $fields = $this->getMarcRecord()->getFields($field);
        if (!is_array($fields)) {
            return $matches;
        }

        foreach ($fields as $currentField) {
            foreach ($data as $key => $info) {
                $split = explode("|", $info);
                if ($split[0] == "msg") {
                    if ($split[1] == "true") {
                        $result = true;
                    } elseif ($split[1] == "false") {
                        $result = false;
                    } else {
                        $result = $split[1];
                    }
                    $matches[$i][$key] = $result;
                } else {

                    if (count($split) < 2) {
                        $subfields = ['a'];
                    } else {
                        $subfields = str_split($split[1]);
                    }
                    $result = $this->getSubfieldArray(
                        $currentField, $subfields, true
                    );
                    $matches[$i][$key] = count($result) > 0
                        ? (string)$result[0] : '';
                }
            }
            $matches[$i]['id'] = $this->getUniqueID();
            $i++;
        }
        return $matches;
    }

    /**
     * Return an XML representation of the record using the specified format.
     *
     * @param string     $format     Name of format to use (corresponds with OAI-PMH
     * metadataPrefix parameter).
     * @param string     $baseUrl    Base URL of host containing VuFind (optional;
     * may be used to inject record URLs into XML when appropriate).
     * @param RecordLink $recordLink Record link helper (optional; may be used to
     * inject record URLs into XML when appropriate).
     *
     * @return mixed         XML, or false if format unsupported.
     */
    public function getXML($format, $baseUrl = null, $recordLink = null)
    {
        // Special case for MARC:
        if ($format == 'marc21') {
            $xml = $this->getMarcRecord()->toXML();
            $xml = str_replace(
                [chr(27), chr(28), chr(29), chr(30), chr(31)], ' ', $xml
            );
            $xml = simplexml_load_string($xml);
            if (!$xml || !isset($xml->record)) {
                return false;
            }

            $xml->record->addAttribute('xmlns', "http://www.loc.gov/MARC21/slim");
            $xml->record->addAttribute(
                'xsi:schemaLocation',
                'http://www.loc.gov/MARC21/slim ' .
                'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd',
                'http://www.w3.org/2001/XMLSchema-instance'
            );
            $xml->record->addAttribute('type', 'Bibliographic');
            return $xml->record->asXML();
        }

        // Try the parent method:
        return parent::getXML($format, $baseUrl, $recordLink);
    }

    /**
     * Attach an ILS connection and related logic to the driver
     *
     * @param \VuFind\ILS\Connection       $ils            ILS connection
     * @param \VuFind\ILS\Logic\Holds      $holdLogic      Hold logic handler
     * @param \VuFind\ILS\Logic\TitleHolds $titleHoldLogic Title hold logic handler
     *
     * @return void
     */
    public function attachILS(\VuFind\ILS\Connection $ils,
        \VuFind\ILS\Logic\Holds $holdLogic,
        \VuFind\ILS\Logic\TitleHolds $titleHoldLogic
    ) {
        $this->ils = $ils;
        $this->holdLogic = $holdLogic;
        $this->titleHoldLogic = $titleHoldLogic;
    }

    /**
     * Do we have an attached ILS connection?
     *
     * @return bool
     */
    protected function hasILS()
    {
        return null !== $this->ils;
    }

    /**
     * Get an array of information about record holdings, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHoldings()
    {
        return $this->hasILS() ? $this->holdLogic->getHoldings(
            $this->getUniqueID(), $this->getConsortialIDs()
        ) : [];
    }

    /**
     * Get an array of information about record history, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHistory()
    {
        // Get Acquisitions Data
        if (!$this->hasILS()) {
            return [];
        }
        try {
            return $this->ils->getPurchaseHistory($this->getUniqueID());
        } catch (ILSException $e) {
            return [];
        }
    }

    /**
     * Get a link for placing a title level hold.
     *
     * @return mixed A url if a hold is possible, boolean false if not
     */
    public function getRealTimeTitleHold()
    {
        if ($this->hasILS()) {
            $biblioLevel = strtolower($this->getBibliographicLevel());
            if ("monograph" == $biblioLevel || strstr($biblioLevel, "part")) {
                if ($this->ils->getTitleHoldsMode() != "disabled") {
                    return $this->titleHoldLogic->getHold($this->getUniqueID());
                }
            }
        }

        return false;
    }

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        return $this->hasILS();
    }

    /**
     * Get access to the raw File_MARC object.
     *
     * @return \File_MARCBASE
     */
    public function getMarcRecord()
    {
        if (null === $this->lazyMarcRecord) {
            $marc = trim($this->fields['fullrecord']);

            // check if we are dealing with MARCXML
            if (substr($marc, 0, 1) == '<') {
                $marc = new \File_MARCXML($marc, \File_MARCXML::SOURCE_STRING);
            } else {
                $marc = str_replace(
                    ['#29;', '#30;', '#31;'], ["\x1D", "\x1E", "\x1F"], $marc
                );
                $marc = new \File_MARC($marc, \File_MARC::SOURCE_STRING);
            }

            $this->lazyMarcRecord = $marc->next();
            if (!$this->lazyMarcRecord) {
                throw new \File_MARC_Exception('Cannot Process MARC Record');
            }
        }

        return $this->lazyMarcRecord;
    }

    /**
     * Get an XML RDF representation of the data in this record.
     *
     * @return mixed XML RDF data (empty if unsupported or error).
     */
    public function getRDFXML()
    {
        return XSLTProcessor::process(
            'record-rdf-mods.xsl', trim($this->getMarcRecord()->toXML())
        );
    }

    /**
     * Return the list of "source records" for this consortial record.
     *
     * @return array
     */
    public function getConsortialIDs()
    {
        return $this->getFieldArray('035', 'a', true);
    }

    /**
     * Method for legacy compatibility with marcRecord property.
     *
     * @param string $key Key to access.
     *
     * @return mixed
     */
    public function __get($key)
    {
        if ($key === 'marcRecord') {
            // property deprecated as of release 2.5.
            trigger_error(
                'marcRecord property is deprecated; use getMarcRecord()',
                E_USER_DEPRECATED
            );
            return $this->getMarcRecord();
        }
        return null;
    }


    public function getAvailFacet()
    {
        return isset($this->fields['avail_facet']) ?
            $this->fields['avail_facet'] : 'avail_facet';
    }

    public function getThumbnail($size = 'small')
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

    public function getRelatedDocumentKVO()
    {
        if ($this->fields['institution'][0] == 'NKP') {
          return array();
        }

        $result = array();

        $fields = $this->getMarcRecord()->getFields('787');
        if (!is_array($fields)) {
            return $result;
        }

        $i=1;

        $existing_fields = 0;

        foreach ($fields as $currentField) {
            $value = [];
            $existing_fields = 0;
            foreach (array('a', 'd', 'g', 'i', 'n', 't', 'x', 'h', '4') as $subfield) {
                if ($currentField->getSubfield($subfield)) {
                    $tmp = '';
                    $tmp = $currentField->getSubfield($subfield)->getData();
                    if (!empty($tmp)) {
                      $value[$subfield] = $tmp;
                      $existing_fields++;
                    }
                }
            }

            if ($this->fields['institution'][0] == 'MUA') {
              $result[$i]['text'] = ($value['a'] ? $value['a']: '') . ($value['t'] ? ', ' . $value['t'] : '')  . ($value['g'] ? ', ' . $value['g'] : '') . ($value['h'] ? ', ' . $value['h'] : '') . ($value['d'] ? ', ' . $value['d'] : '');
            } else {
              $result[$i]['text'] = ($value['a'] ? $value['a'] . ':' : '') . ($value['t'] ? $value['t'] : '') . ($value['4']? "[" . $value['4'] . "]" : '') . ($value['d'] ? " " . $value['d'] : '') . ($value['x'] ? " -- " . $value['x'] : '') . ($value['n'] ? " -- " . $value['n'] : '') . ($value['g'] ? ", " . $value['g'] : '') . ($value['h'] ? ", " . $value['h'] : '');

            }
            $i++;
        }

        if ($existing_fields > 0) {
          return $result;
        } else {
          return null;
        }

    }

    public function getPublisherNotes() {
      $retVal = array();
      $ind1 = "";
      $ind2 = "";

      $fields = $this->getMarcRecord()->getFields('550');
      if (!empty($fields)) {
        foreach ($fields as $field) {
            $ind1 = $field->getIndicator('1');
            $subfieldA = $field->getSubfield('a');
            if ($subfieldA and ($ind1 != '9')) {
            $subfieldA = $subfieldA->getData();
            $retVal[] = $subfieldA;
            }
        }
      }

      return $retVal;
    }

    public function getLocation() {
        $retVal = array();

        $fields = $this->getMarcRecord()->getFields('982');

        if (!empty($fields)) {
            $glued = array();
            $wanted = array('h', 'c' , 's', 'b', 'p', 'd');

            foreach ($fields as $field) {
              $toglue = array();
              foreach($wanted as $letter){
                foreach ($field->getSubfields($letter) as $item) {
                  $toglue[] = $item->getData();
                }
              }
              $glued[] = $this->glue($toglue, ', ');
            }
            $retVal['loc_text'] = $glued;
        }

        $fields = $this->getMarcRecord()->getFields('LOK');
        if (!empty($fields)) {
            foreach ($fields as $field) {
              if ($field->getSubfield('l')) {
                $retVal['loc_code'][] = $field->getSubfield('l')->getData();
              }
            }
        }

        return $retVal;
    }

    public function getInternalParts() {
        $retVal = array();
        $fields = $this->getMarcRecord()->getFields('505');
        if ($fields) {
            foreach ($fields as $field) {
        $ind1 = $field->getIndicator('1');
        if ($ind1 != '0') {
          $subfieldG = $field->getSubfield('g');
          $subfieldT = $field->getSubfield('t');
          if ($subfieldG) {
              $subfieldG = $subfieldG->getData();
          }
          if ($subfieldT) {
              $subfieldT = $subfieldT->getData();
          }
          if ($subfieldT OR $subfieldG) {
            $retVal[] = $subfieldG . " " . $subfieldT;
          }
                }
            }
        }
        return $retVal;
    }

    public function getTypoAnalysis() {
        $retVal = array();
        $fields = $this->getMarcRecord()->getFields('505');
        if ($fields) {
            foreach ($fields as $field) {
                $subfieldA = $field->getSubfield('a');
                if ($subfieldA) {
                    $subfieldA = $subfieldA->getData();
                    $retVal[] = $subfieldA;
                }
            }
        }
        return $retVal;
    }

    public function getBinding() {
      $retVal = array();
      $fields = $this->getMarcRecord()->getFields('VAZ');

      if (!empty($fields)) {
        $glued = array();
        $wanted = array('a', 'j' , 'b', 'e');

        foreach ($fields as $field) {
          $toglue = array();
          foreach($wanted as $letter){
            foreach ($field->getSubfields($letter) as $item) {
              $toglue[] = $item->getData();
            }
          }

          $glued[] = $this->glue($toglue, ", ");


          if ($field->getSubfield('d')) {
            $retVal['bind_ornam']= $field->getSubfield('d')->getData();
          }
          if ($field->getSubfield('g')) {
            $retVal['bind_iron']= $field->getSubfield('g')->getData();
          }
          if ($field->getSubfield('i')) {
            $retVal['bind_size']= $field->getSubfield('i')->getData();
          }
          if ($field->getSubfield('h')) {
            $retVal['bind_clips']= $field->getSubfield('h')->getData();
          }
        }
        if (!empty($toglue)) {
          $retVal['bind'] = $glued;
        }
      }
      return $retVal;
    }

    public function getProvenance() {
      $retVal = array();

      $fields = $this->getMarcRecord()->getFields('711');
      if (!empty($fields)) {
        foreach ($fields as $field) {
          $subfield4 = $field->getSubfield('4')?$field->getSubfield('4')->getData():null ;
          $subfieldA = $field->getSubfield('a')?$field->getSubfield('a')->getData():null ;
          $subfieldB = $field->getSubfield('b')?$field->getSubfield('b')->getData():null ;
          if ($subfield4 == 'dnr') {
            $retVal['prov_dnr'][] = $this->glue(array($subfieldA,$subfieldB), ", ");
          }
          if ($subfield4 == 'fmo') {
            $retVal['prov_fmo'][] = $this->glue(array($subfieldA,$subfieldB), " ");
          }
        }
      }

      $fields = $this->getMarcRecord()->getFields('RK2');
      if (!empty($fields)) {
        foreach ($fields as $field) {
          $glued = null;
          $subfieldF = $field->getSubfield('f')?$field->getSubfield('f')->getData():null ;
          $subfieldS = $field->getSubfield('s')?$field->getSubfield('s')->getData():null ;
          $subfieldV = $field->getSubfield('v')?$field->getSubfield('v')->getData():null ;
          $subfieldK = $field->getSubfield('k')?$field->getSubfield('k')->getData():null ;

          if ($subfieldS) {
            $retVal['prov_oldsig'][] = $this->glue(array($subfieldF,$subfieldS), ": ");
          }
          if ($subfieldV) {
            $retVal['prov_attach'][] = $this->glue(array($subfieldF,$subfieldV), ": ");
          }
          if ($subfieldK) {
            $retVal['prov_kolofon'][] = $this->glue(array($subfieldF,$subfieldK), ": ");
          }

        }
      }

      $fields = $this->getMarcRecord()->getFields('561');
      if (!empty($fields)) {
        foreach ($fields as $field) {
          if ($field->getSubfield('a')) {
            $retVal['prov_note'][] = $field->getSubfield('a')->getData();
          }
        }
      }

      $fields = $this->getMarcRecord()->getFields('562');
       if (!empty($fields)) {
        foreach ($fields as $field) {
          if ($field->getSubfield('a')) {
            $retVal['prov_other_note'][] = $field->getSubfield('a')->getData();
          }
        }
      }
      return $retVal;
    }

    public function getPreservation() {
      $retVal = array();

      $fields = $this->getMarcRecord()->getFields('RK9');
      if (!empty($fields)) {
        foreach ($fields as $field) {
          if ($field->getSubfield('p')) {
            $retVal['pres_fabric'][] = $field->getSubfield('p')->getData();
          }
          if ($field->getSubfield('v')) {
            $retVal['pres_bind'][] = $field->getSubfield('v')->getData();
          }
        }
      }
      return $retVal;
    }

    public function getNkpLink() {
      $retVal = "";

      $fields = $this->getMarcRecord()->getFields('998');
      if (!empty($fields)) {
        foreach ($fields as $field) {
          if ($field->getSubfield('a')) {
            $retVal = $field->getSubfield('a')->getData();
          }
        }
      }
      return $retVal;
    }

    public function getEdition() {
      $retVal = "";

      $fields = $this->getMarcRecord()->getFields('490');
      if (!empty($fields)) {
        foreach ($fields as $field) {
	  $tmp = null;
          if ($field->getSubfield('a')) {
	    $tmp = $field->getSubfield('a')->getData();
	    if ($field->getSubfield('v')) {
	      $tmp = $tmp . " " . $field->getSubfield('v')->getData();
	    }
          }
        }
        $retVal[] = $tmp;
      }
      return $retVal;
    }

    public function getAddons() {
      $retVal = array();

      $fields = $this->getMarcRecord()->getFields('DOD');
      if (!empty($fields)) {
        foreach ($fields as $field) {
          if ($field->getSubfield('a')) {
            $retVal[] = $field->getSubfield('a')->getData();
          }
        }
      }
      return $retVal;
    }

    public function getExemplars() {

        $retVal = array();
        $fields = $this->getMarcRecord()->getFields('535');

        if ($fields) {
            foreach ($fields as $field) {
                // Is there an address in the current field?
                $subfieldA = $field->getSubfield('a');
                $subfieldB = $field->getSubfield('b');
                $subfield9 = $field->getSubfield('9');
                $sfield9 = !empty($subfield9) ? " - " . $subfield9->getData() : "";

                $subfieldsG = $field->getSubfields('g');
                $subfieldG = null;

                foreach ($subfieldsG as $sub) {
                    $subfieldG .= $sub->getData();
                }

                if ($subfieldA) {
                    $subfieldA = $subfieldA->getData();
                }
                if ($subfieldB) {
                    $subfieldB = $subfieldB->getData();
                }
                if ($subfieldG) {
                    $subfieldG = " (" . $subfieldG . ")";
                }

                if (empty($subfieldB)) {
                    $retVal[] = $subfieldA . $sfield9 . $subfieldG;
                } else {
                    $retVal[] = $subfieldB . " : " . $subfieldA . $sfield9 . $subfieldG;
                }

            }
        }

        $fields = $this->getMarcRecord()->getFields('910');

        if (($this->fields['record_source'] == "nkp") && $fields) {
            foreach ($fields as $field) {
            $owner = '';
            $subfieldA = $field->getSubfield('a');
            $subfieldB = $field->getSubfield('b');
            $subfieldP = $field->getSubfield('p');

            $owner = $subfieldA ? $subfieldA->getData() : '';
            $owner = $subfieldB ? $owner . " -- sign. " . $subfieldB->getData() : $owner;
            $owner = $subfieldP ? $owner . " (" . $subfieldP->getData() . ")": $owner;

            if ($owner != '') {
                $retVal[] = $owner;
            }

            }
        }

        return $retVal;
    }

    /**
    *   načte linky na obrázky z X00 pole
    */
    public function getImageLinks() {

      $images = array();

      $clavius = $this->mainConfig->Links->clavius_catalog;

      $fields = $this->getMarcRecord()->getFields('X00');
      if ($fields) {
          foreach ($fields as $field) {
              $subfieldT = $field->getSubfield('t');
              $subfieldO = $field->getSubfield('o');
              if ($subfieldO) {
                  $subfieldO = $subfieldO->getData();
                  if ($subfieldT) {
                    $subfieldT = $subfieldT->getData();
                  }
                  $images[] = array(
                    'desc' => $subfieldT,
                    'link' => $clavius . $subfieldO
                  );
              }
          }
      }

      return $images;
    }

    public function getURLs()
    {
        $retVal = array();

        $source = $this->getRecordSource();
        $urls = $this->getMarcRecord()->getFields('856');
        if ($urls) {
            foreach ($urls as $url) {
                $address = $url->getSubfield('u');
                if ($address) {
                    $address = $address->getData();
                    if ($url->getSubfield('t')) {
		      $desc = $url->getSubfield('t')->getData();
                    } else if ($url->getSubfield('y')) {
		      $desc = $url->getSubfield('y')->getData();
                    } else {
		      $desc = $this->translate('Digital document');
                    }

                    if ($source == "clavius") {
		      $desc = $this->translate('Digital document');
                    }

                    $retVal[] = array('desc' => $desc, 'link' => $address);
                }
            }
        }

        $urls = $this->getMarcRecord()->getFields('X00');
        if ($urls) {
            foreach ($urls as $url) {
                $address = $url->getSubfield('u');
                $illustration = $url->getSubfield('o');
                if ($address) {
                    $address = $address->getData();
                    if ($url->getSubfield('t')) {
		      $desc = $url->getSubfield('t')->getData();
                    } else if ($url->getSubfield('y')) {
		      $desc = $url->getSubfield('y')->getData();
                    } else {
		      $desc = $address;
                    }
                    if ($source == "clavius") {
		      $desc = $this->translate('Digital document');
                    }

                    $retVal[] = array('desc' => $desc, 'link' => $address);
                } else if (empty($illustration)) {
		  if ($url->getSubfield('t')) {
		    $desc = $url->getSubfield('t')->getData();
		  } else if ($url->getSubfield('y')) {
		    $desc = $url->getSubfield('y')->getData();
		  }
		  if (!empty($desc)) {
		    $retVal[] = array('desc' => $desc, 'link' => null);
		  }
                }
            }
        }

        return $retVal;
    }


    public function getSourceDocument() {

        $result = "";

        $fields = $this->getMarcRecord()->getFields('773');

        if ($fields) {
          foreach ($fields as $field) {
              $subfieldT = $field->getSubfield('t');
              $subfieldG = $field->getSubfield('g');
              if ($subfieldT) {
                  $subfieldT = $subfieldT->getData();
                  if ($subfieldG) {
                    $subfieldG = $subfieldG->getData();
                  }
                  $result = $this->glue([$subfieldT, $subfieldG], ": ");
              }
          }
        }

        $recordSource = $this->getRecordSource();
        $details = '';
	if ($recordSource == "mua-kod") {
	  $zaz = $this->getMarcRecord()->getFields('ZAZ');
	  if (!empty($zaz)) {
	    foreach ($zaz as $field) {
	      $volume = !empty($field->getSubfield('s')) ? $this->translate("volume") . ": " . $field->getSubfield('s')->getData() : '';
	      $year = !empty($field->getSubfield('d')) ? $this->translate("publication year") . ": " . $field->getSubfield('d')->getData(): '';
	      $pages = !empty($field->getSubfield('l')) ? $field->getSubfield('l')->getData(): '';

	      $result = $this->glue([$result, $volume, $year, $pages], ", ");
	    }
	  }
	}

        return $result;
    }

    public function getGoogleBooksLinks()
    {
        $result = [];

        $gbs_link = "http://books.google.com/books/content?printsec=frontcover&img=1&zoom=2&id=";

        $coreURLs = $this->getURLs();
        foreach ($coreURLs as $key => $value) {
          $parsed_query = [];
          if (strpos($value['link'], "books.google") !== false) {
            $parsed_url = parse_url($value['link']);
            parse_str($parsed_url['query'], $parsed_query);

	    if (!empty($parsed_query['id'])) {
	      $result[] = ['link' => $value['link'], 'thumbnail' => $gbs_link . $parsed_query['id']];
            }
          }
        }

        return $result;
    }

    

    public function glue($values, $separator = ' - ' )
    {
      $return = null;
      $count = 0;

      foreach($values as $item) {
        if ($item) {
          $return = ($count<1)?$return . $item: $return . $separator . $item;
          $count++;
        }
      }

      return trim($return);
    }

}
