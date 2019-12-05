<?php

namespace knav\ILS\Driver;
use VuFind\Exception\ILS as ILSException;
use VuFind\Exception\Date as DateException;
use VuFind\I18n\Translator\TranslatorAwareInterface;


/**
 * Aleph Translator Class
 */
class AlephTranslator
{
    /**
     * Constructor
     *
     * @param array $configArray Aleph configuration
     */
    public function __construct($configArray)
    {
        $this->charset = $configArray['util']['charset'];
        $this->table15 = $this->parsetable(
            $configArray['util']['tab15'],
            get_class($this) . "::tab15Callback"
        );
        $this->table40 = $this->parsetable(
            $configArray['util']['tab40'],
            get_class($this) . "::tab40Callback"
        );
        $this->table_sub_library = $this->parsetable(
            $configArray['util']['tab_sub_library'],
            get_class($this) . "::tabSubLibraryCallback"
        );
    }

    /**
     * Parse a table
     *
     * @param string $file     Input file
     * @param string $callback Callback routine for parsing
     *
     * @return string
     */
    public function parsetable($file, $callback)
    {
        $result = [];
        $file_handle = fopen($file, "r, ccs=UTF-8");
        $rgxp = "";
        while (!feof($file_handle)) {
            $line = fgets($file_handle);
            $line = chop($line);
            if (preg_match("/!!/", $line)) {
                $line = chop($line);
                $rgxp = AlephTranslator::regexp($line);
            } if (preg_match("/!.*/", $line) || $rgxp == "" || $line == "") {
            } else {
                $line = str_pad($line, 80);
                $matches = "";
                if (preg_match($rgxp, $line, $matches)) {
                    call_user_func_array(
                        $callback, [$matches, &$result, $this->charset]
                    );
                }
            }
        }
        fclose($file_handle);
        return $result;
    }

    /**
     * Get a tab40 collection description
     *
     * @param string $collection Collection
     * @param string $sublib     Sub-library
     *
     * @return string
     */
    public function tab40Translate($collection, $sublib)
    {
        $findme = $collection . "|" . $sublib;
        $desc = $this->table40[$findme];
        if ($desc == null) {
            $findme = $collection . "|";
            $desc = $this->table40[$findme];
        }
        return $desc;
    }

    /**
     * Support method for tab15Translate -- translate a sub-library name
     *
     * @param string $sl Text to translate
     *
     * @return string
     */
    public function tabSubLibraryTranslate($sl)
    {
        return $this->table_sub_library[$sl];
    }

    /**
     * Get a tab15 item status
     *
     * @param string $slc  Sub-library
     * @param string $isc  Item status code
     * @param string $ipsc Item process status code
     *
     * @return string
     */
    public function tab15Translate($slc, $isc, $ipsc)
    {
        $tab15 = $this->tabSubLibraryTranslate($slc);
        if ($tab15 == null) {
            print "tab15 is null!<br>";
        }
        $findme = $tab15["tab15"] . "|" . $isc . "|" . $ipsc;
        $result = $this->table15[$findme];

        if ($result == null) {
            $findme = $tab15["tab15"] . "||" . $ipsc;
            $result = $this->table15[$findme];
        }
        $result["sub_lib_desc"] = $tab15["desc"];
        return $result;
    }

    /**
     * Callback for tab15 (modify $tab15 by reference)
     *
     * @param array  $matches preg_match() return array
     * @param array  $tab15   result array to generate
     * @param string $charset character set
     *
     * @return void
     */
    public static function tab15Callback($matches, &$tab15, $charset)
    {
        $lib = $matches[1];
        $no1 = $matches[2];
        if ($no1 == "##") {
            $no1 = "";
        }
        $no2 = $matches[3];
        if ($no2 == "##") {
            $no2 = "";
        }
        $desc = iconv($charset, 'UTF-8', $matches[5]);
        $key = trim($lib) . "|" . trim($no1) . "|" . trim($no2);
        $tab15[trim($key)] = [
            "desc" => trim($desc), "loan" => $matches[6], "request" => $matches[8],
            "opac" => $matches[10]
        ];
    }

    /**
     * Callback for tab40 (modify $tab40 by reference)
     *
     * @param array  $matches preg_match() return array
     * @param array  $tab40   result array to generate
     * @param string $charset character set
     *
     * @return void
     */
    public static function tab40Callback($matches, &$tab40, $charset)
    {
        $code = trim($matches[1]);
        $sub = trim($matches[2]);
        $sub = trim(preg_replace("/#/", "", $sub));
        $desc = trim(iconv($charset, 'UTF-8', $matches[4]));
        $key = $code . "|" . $sub;
        $tab40[trim($key)] = [ "desc" => $desc ];
    }

    /**
     * Sub-library callback (modify $tab_sub_library by reference)
     *
     * @param array  $matches         preg_match() return array
     * @param array  $tab_sub_library result array to generate
     * @param string $charset         character set
     *
     * @return void
     */
    public static function tabSubLibraryCallback($matches, &$tab_sub_library,
        $charset
    ) {
        $sublib = trim($matches[1]);
        $desc = trim(iconv($charset, 'UTF-8', $matches[5]));
        $tab = trim($matches[6]);
        $tab_sub_library[$sublib] = [ "desc" => $desc, "tab15" => $tab ];
    }

    /**
     * Apply standard regular expression cleanup to a string.
     *
     * @param string $string String to clean up.
     *
     * @return string
     */
    public static function regexp($string)
    {
        $string = preg_replace("/\\-/", ")\\s(", $string);
        $string = preg_replace("/!/", ".", $string);
        $string = preg_replace("/[<>]/", "", $string);
        $string = "/(" . $string . ")/";
        return $string;
    }
}

/**
 * ILS Exception
 */
class AlephRestfulException extends ILSException
{
    /**
     * XML response (false for none)
     *
     * @var string|bool
     */
    protected $xmlResponse = false;

    /**
     * Attach an XML response to the exception
     *
     * @param string $body XML
     *
     * @return void
     */
    public function setXmlResponse($body)
    {
        $this->xmlResponse = $body;
    }

    /**
     * Return XML response (false if none)
     *
     * @return string|bool
     */
    public function getXmlResponse()
    {
        return $this->xmlResponse;
    }
}

class Aleph extends \VuFind\ILS\Driver\Aleph implements TranslatorAwareInterface
{
	use \VuFind\I18n\Translator\TranslatorAwareTrait;

	protected $alephTranslator = false;

	public function init()
    {
        // Validate config
        $required = [
            'host', 'bib', 'useradm', 'admlib', 'dlfport', 'available_statuses'
        ];
        foreach ($required as $current) {
            if (!isset($this->config['Catalog'][$current])) {
                throw new ILSException("Missing Catalog/{$current} config setting.");
            }
        }
        if (!isset($this->config['sublibadm'])) {
            throw new ILSException('Missing sublibadm config setting.');
        }

        // Process config
        $this->host = $this->config['Catalog']['host'];
        $this->bib = explode(',', $this->config['Catalog']['bib']);
        $this->useradm = $this->config['Catalog']['useradm'];
        $this->admlib = $this->config['Catalog']['admlib'];
        if (isset($this->config['Catalog']['wwwuser'])
            && isset($this->config['Catalog']['wwwpasswd'])
        ) {
            $this->wwwuser = $this->config['Catalog']['wwwuser'];
            $this->wwwpasswd = $this->config['Catalog']['wwwpasswd'];
            $this->xserver_enabled = true;
            $this->xport = isset($this->config['Catalog']['xport'])
                ? $this->config['Catalog']['xport'] : 80;
        } else {
            $this->xserver_enabled = false;
        }
        $this->dlfport = $this->config['Catalog']['dlfport'];
        $this->sublibadm = $this->config['sublibadm'];
        if (isset($this->config['duedates'])) {
            $this->duedates = $this->config['duedates'];
        }
        $this->available_statuses
            = explode(',', $this->config['Catalog']['available_statuses']);
        $this->quick_availability
            = isset($this->config['Catalog']['quick_availability'])
            ? $this->config['Catalog']['quick_availability'] : false;
        $this->debug_enabled = isset($this->config['Catalog']['debug'])
            ? $this->config['Catalog']['debug'] : false;
        if (isset($this->config['util']['tab40'])
            && isset($this->config['util']['tab15'])
            && isset($this->config['util']['tab_sub_library'])
        ) {
            if (isset($this->config['Cache']['type'])
                && null !== $this->cacheManager
            ) {
                $cache = $this->cacheManager
                    ->getCache($this->config['Cache']['type']);
                $this->alephTranslator = $cache->getItem('alephTranslator');
            }
            if ($this->alephTranslator == false) {
                $this->alephTranslator = new AlephTranslator($this->config);
                if (isset($cache)) {
                    $cache->setItem('alephTranslator', $this->alephTranslator);
                }
            }
        }
        if (isset($this->config['Catalog']['preferred_pick_up_locations'])) {
            $this->preferredPickUpLocations = explode(
                ',', $this->config['Catalog']['preferred_pick_up_locations']
            );
        }
        if (isset($this->config['Catalog']['default_patron_id'])) {
            $this->defaultPatronId = $this->config['Catalog']['default_patron_id'];
        }

        $this->maxRenewCount = $this->config['Catalog']['max_renewals'] ? $this->config['Catalog']['max_renewals'] : 3;

        $url_parts = explode('/', $_SERVER['REQUEST_URI']);
        $inst = empty($url_parts[1])? '' : strtoupper($url_parts[1]);

    }

    protected function doRestDLFRequest($path_elements, $params = null,
        $method = 'GET', $body = null
    ) {
        $path = '';

        $requestLang = $this->translate('aleph_lang');

        foreach ($path_elements as $path_element) {
            $path .= $path_element . "/";
        }

        $params["lang"] = $requestLang;

        $url = "http://$this->host:$this->dlfport/rest-dlf/" . $path;
        $url = $this->appendQueryString($url, $params);
        $result = $this->doHTTPRequest($url, $method, $body);
        $replyCode = (string) $result->{'reply-code'};
        if ($replyCode != "0000") {
            $replyText = (string) $result->{'reply-text'};
            $this->logError(
                "DLF request failed", [
                    'url' => $url, 'reply-code' => $replyCode,
                    'reply-message' => $replyText
                ]
            );
            $ex = new AlephRestfulException($replyText, $replyCode);
            $ex->setXmlResponse($result);
            throw $ex;
        }
        return $result;
    }

    public function getMyProfile($user)
    {
        if ($this->xserver_enabled) {
            return $this->getMyProfileX($user);
        } else {
            return $this->getMyProfileDLF($user);
        }
    }

    public function getMyProfileX($user)
    {
        $recordList = [];
        if (!isset($user['college'])) {
            $user['college'] = $this->useradm;
        }
        $xml = $this->doXRequest(
            "bor-info",
            [
                'loans' => 'N', 'cash' => 'N', 'hold' => 'N',
                'library' => $user['college'], 'bor_id' => $user['id']
            ], true
        );

        $id = (string) $xml->z303->{'z303-id'};
        $address1 = (string) $xml->z304->{'z304-address-2'};
        $address2 = (string) $xml->z304->{'z304-address-3'};
        $zip = (string) $xml->z304->{'z304-zip'};
        $phone = (string) $xml->z304->{'z304-telephone'};
        $barcode = (string) $xml->z304->{'z304-address-0'};
        $group = (string) $xml->z305->{'z305-bor-status'};
        $expiry = (string) $xml->z305->{'z305-expiry-date'};
        $credit_sum = (string) $xml->z305->{'z305-sum'};
        $credit_sign = (string) $xml->z305->{'z305-credit-debit'};
        $name = (string) $xml->z303->{'z303-name'};
        if (strstr($name, ",")) {
            list($lastname, $firstname) = explode(",", $name);
        } else {
            $lastname = $name;
            $firstname = "";
        }
        if ($credit_sign == null) {
            $credit_sign = "C";
        }
        $recordList['firstname'] = $firstname;
        $recordList['lastname'] = $lastname;
        if (isset($user['email'])) {
            $recordList['email'] = $user['email'];
        }
        $recordList['address1'] = $address1;
        $recordList['address2'] = $address2;
        $recordList['zip'] = $zip;
        $recordList['phone'] = $phone;
        $recordList['group'] = $group;
        $recordList['barcode'] = $barcode;
        $recordList['expire'] = $this->parseDate($expiry);
        $recordList['credit'] = $expiry;
        $recordList['credit_sum'] = $credit_sum;
        $recordList['credit_sign'] = $credit_sign;
        $recordList['id'] = $id;
        return $recordList;
    }


    public function getMyProfileDLF($user)
    {
        $xml = $this->doRestDLFRequest(
            ['patron', $user['id'], 'patronInformation', 'address']
        );
        $address = $xml->xpath('//address-information');
        $address = $address[0];
        $address1 = (string)$address->{'z304-address-1'};
        $address2 = (string)$address->{'z304-address-2'};
        $address3 = (string)$address->{'z304-address-3'};
        $zip = (string)$address->{'z304-zip'};
        $phone = (string)$address->{'z304-telephone-1'};
        $email = (string)$address->{'z304-email-address'};
        $dateFrom = (string)$address->{'z304-date-from'};
        $dateTo = (string)$address->{'z304-date-to'};

        if (strstr($address1, " ")) {
           list($recordList['lastname'], $recordList['firstname']) = explode(" ", $address1);
        } else {
           $recordList['lastname'] = $address1;
           $recordList['firstname'] = "";
        }

        $recordList['street'] = $address2;
        $recordList['city'] = $address3;
        $recordList['zip'] = $zip;
        $recordList['phone'] = $phone;
        $recordList['email'] = $email;
        $recordList['dateFrom'] = $dateFrom;
        $recordList['dateTo'] = $dateTo;
        $recordList['id'] = $user['id'];
        $xml = $this->doRestDLFRequest(
            ['patron', $user['id'], 'patronStatus', 'registration']
        );
        $status = $xml->xpath("//institution/z305-bor-status");
        $expiry = $xml->xpath("//institution/z305-expiry-date");
        $recordList['expire'] = $this->parseDate($expiry[0]);
        $recordList['group'] = $status[0];
        return $recordList;
    }


    public function getMyTransactions($user, $history = false)
    {
        $userId = $user['id'];
        $transList = [];
        $params = ["view" => "full"];
        if ($history) {
            $params["type"] = "history";
        }
        $xml = $this->doRestDLFRequest(
            ['patron', $userId, 'circulationActions', 'loans'], $params
        );
        foreach ($xml->xpath('//loan') as $item) {
            $z36 = $item->z36;
            $z13 = $item->z13;
            $z30 = $item->z30;
            $fine = $item->{'fine'} ? $item->{'fine'} : 0;
            $group = $item->xpath('@href');
            $group = substr(strrchr($group[0], "/"), 1);

            $renew_att = $item->xpath('@renew');

            $docno = (string) $z36->{'z36-doc-number'};
            $location = (string) $z36->{'z36_pickup_location'};
            $reqnum = (string) $z36->{'z36-doc-number'}
                . (string) $z36->{'z36-item-sequence'}
                . (string) $z36->{'z36-sequence'};
            $due = $returned = null;

            if ($history) {
                $due = $item->z36h->{'z36h-due-date'};
                $returned = $item->z36h->{'z36h-returned-date'};
                $loaned = (string) $item->z36h->{'z36h-loan-date'};
                $renewCount = $item->z36h->{'z36h-no-renewal'};
                $lastRenewDate = (string) $item->z36h->{'z36h-last-renew-date'};
            } else {
                $due = (string) $z36->{'z36-due-date'};
                $loaned = (string) $z36->{'z36-loan-date'};
                $renewCount = $z36->{'z36-no-renewal'};
                $lastRenewDate = (string) $z36->{'z36-last-renew-date'};
            }
            $today = date("Ymd");

            $norenew_status = '';
            if ($renewCount >= $this->maxRenewCount ) {
                $norenew_status = 'norenew_maxcount';
            }

            $title = (string) $z13->{'z13-title'};
            $author = (string) $z13->{'z13-author'};
            $isbn = (string) $z13->{'z13-isbn-issn'};
            $barcode = (string) $z30->{'z30-barcode'};
            $transList[] = [
                'id' => $docno,
                'item_id' => $group,
                'location' => $location,
                'title' => $title,
                'author' => $author,
                'isbn' => [$isbn],
                'reqnum' => $reqnum,
                'docno' => $docno,
                'barcode' => $barcode,
                'duedate' => $this->parseDate($due),
                'loandate' => $this->parseDate($loaned),
                'returned' => $this->parseDate($returned),
                'renewCount' => $renewCount,
                'norenew_single_status' => $norenew_status,
                'lastRenew' => $this->parseDate($lastRenewDate),
                'renewable' => ($renew_att == "N")?false:true,
                'fine' => $fine,
                'overdue' => ($due < $today)?true:false,
                'norenew_single_status' => $norenew_status,
            ];
        }
        return $transList;
    }

    public function getMyStudyRooms($user)
    {
        $result = array();
        return $result;
    }

    public function getMyFines($user)
    {
        $finesList = [];
        $finesListSort = [];
        $finesListLoans = array();
        $result = [];

        $xml = $this->doRestDLFRequest(['patron', $user['id'], 'circulationActions', 'cash'],["view" => "full"]);

        foreach ($xml->xpath('//cash') as $item) {
            $z31 = $item->z31;
            $z13 = $item->z13;
            $z30 = $item->z30;
            $title = (string) $z13->{'z13-title'};
            $transactiondate = date('d-m-Y', strtotime((string) $z31->{'z31-date'}));
            $transactiontype = (string) $z31->{'z31-credit-debit'};
            $id = (string) $z13->{'z13-doc-number'};
            $barcode = (string) $z30->{'z30-barcode'};
            $checkout = (string) $z31->{'z31-date'};
            $description = (string) $z31->{'z31-description'};
            $id = $this->barcodeToID($barcode);
            $mult = 100;
            $amount
                = (float)(preg_replace("/[\(\)]/", "", (string) $z31->{'z31-sum'}))
                * $mult;
            $cashref = (string) $z31->{'z31-sequence'};
            $balance = 0;

            $finesListSort["$cashref"]  = [
                    "title"   => $title,
                    "barcode" => $barcode,
                    "amount" => $amount,
                    "transactiondate" => $transactiondate,
                    "transactiontype" => $transactiontype,
                    "checkout" => $this->parseDate($checkout),
                    "balance"  => $balance,
                    "description" => $description,
                    "id"  => $id,
                    "onlinepay" => true,
            ];
        }
        ksort($finesListSort);
        foreach (array_keys($finesListSort) as $key) {
            $title = $finesListSort[$key]["title"];
            $barcode = $finesListSort[$key]["barcode"];
            $amount = $finesListSort[$key]["amount"];
            $checkout = $finesListSort[$key]["checkout"];
            $transactiondate = $finesListSort[$key]["transactiondate"];
            $transactiontype = $finesListSort[$key]["transactiontype"];
            $description = $finesListSort[$key]["description"];
            $balance += $finesListSort[$key]["amount"];
            $id = $finesListSort[$key]["id"];
            $onlinepay = $finesListSort[$key]["onlinepay"];
            $finesList[] = [
                "title"   => $title,
                "barcode"  => $barcode,
                "amount"   => $amount,
                "transactiondate" => $transactiondate,
                "transactiontype" => $transactiontype,
                "balance"  => $balance,
                "checkout" => $checkout,
                "description" => $description,
                "id"  => $id,
                "printLink" => "test",
                "onlinepay" => $onlinepay,
            ];
        }

        $xml = $this->doRestDLFRequest(array('patron', $user['id'], 'circulationActions', 'loans'), array("view" => "full"));
        foreach ($xml->xpath('//loan') as $item) {
           $z36 = $item->z36;
           $z13 = $item->z13;
           $z30 = $item->z30;
           $fine = 0;

           if ($item->{'fine'}) {
                $fine = $item->{'fine'};
           }

           if (abs($fine) == 0) continue;

           $title = (string) $z13->{'z13-title'};
           $docno = (string) $z36->{'z36-doc-number'};
           $duedate = (string) $z36->{'z36-due-date'};
           $transactiondate = date('d-m-Y', strtotime((string) $z36->{'z36-due-date'}));
           $author = (string) $z13->{'z13-author'};
           $isbn = (string) $z13->{'z13-isbn-issn'};
           $barcode = (string) $z30->{'z30-barcode'};
           $docno13 = (string) $z13->{'z13-doc-number'};
           $id = (string) $z13->{'z13-doc-number'};
           $balance = 0;
           $mult = -100;
           $fine = (float) abs($fine*$mult);
           $cashref = (string) $z30->{'z30-inventory-number'};
           $finesListLoans[] = array(
                       'id' => $id,
                       'title' => $title,
                       'sysno' => $docno13,
                       'barcode' => $barcode,
                       'amount' => $fine,
                       "balance"  => $balance,
                       "description" => "zpozdné",
                       "checkout" => $this->parseDate($duedate),
                       "transactiondate" => $transactiondate,
                       "onlinepay" => false
                       );
        }

        if (!empty($finesList)) {
      	  $result['cash'] = $finesList;
      	}
      	if (!empty($finesListLoans)) {
      	  $result['loan'] = $finesListLoans;
      	}

        return $result;
    }

    public function getHolding($id, array $patron = null)
    {
        $holding = [];
        list($bib, $sys_no) = $this->parseId($id);
        $resource = $bib . $sys_no;
        $params = ['view' => 'full'];
        if (!empty($patron['id'])) {
            $params['patron'] = $patron['id'];
        } else if (isset($this->defaultPatronId)) {
            $params['patron'] = $this->defaultPatronId;
        }
        $xml = $this->doRestDLFRequest(['record', $resource, 'items'], $params);
        foreach ($xml->{'items'}->{'item'} as $item) {
            $item_status         = (string) $item->{'z30-item-status-code'}; // $isc
            $item_status_code         = (string) $item->{'z30-item-status-code'}; // $isc
            // $ipsc:
            $item_process_status = (string) $item->{'z30-item-process-status-code'};
            $sub_library_code    = (string) $item->{'z30-sub-library-code'}; // $slc

            dump($this->inst);

            $z30 = $item->z30;
            if ($this->alephTranslator) {
                $item_status = $this->alephTranslator->tab15Translate(
                    $sub_library_code, $item_status_code, $item_process_status
                );
            } else {
                $item_status = [
                    'opac'         => 'Y',
                    'request'      => 'C',
                    'desc'         => (string) $z30->{'z30-item-status'},
                    'sub_lib_desc' => (string) $z30->{'z30-sub-library'}
                ];
            }
            $item_status['desc'] = (string) $z30->{'z30-item-status'};
            $item_status['sub_lib_desc'] = (string) $z30->{'z30-sub-library'};
            
            if ($item_status['opac'] != 'Y') {
                continue;
            }
            $availability = false;
            $sub_library = (string) $z30->{'z30-sub-library'}; 
            $collection = (string) $z30->{'z30-collection'};
            $collection_desc = ['desc' => $collection];
            if ($this->alephTranslator) {
                $collection_code = (string) $item->{'z30-collection-code'};
                $collection_desc = $this->alephTranslator->tab40Translate(
                    $collection_code, $sub_library_code
                );
            }
            $requested = false;
            $duedate = '';
            $addLink = false;
            $status = (string) $item->{'status'};
            if (in_array($status, $this->available_statuses)) {
                $availability = true;
            }

            if ($item_status['request'] == 'Y' && $availability == false) {
                $addLink = true;
            }

            $historical_collection = false;
            if ($item_status_code=="73" && ($sub_library_code=="KNAV" || $sub_library_code=="KNAVD")) {
                $historical_collection = true;
            }

            if (!empty($patron)) {
                $hold_request = $item->xpath('info[@type="HoldRequest"]/@allowed');
                $addLink = ($hold_request[0] == 'Y');
            }
            $matches = [];
            if (preg_match(
                "/([0-9]*\\/[a-zA-Z]*\\/[0-9]*);([a-zA-Z ]*)/", $status, $matches
            )) {
                $duedate = $this->parseDate($matches[1]);
                $requested = (trim($matches[2]) == "Requested");
            } else if (preg_match(
                "/([0-9]*\\/[a-zA-Z]*\\/[0-9]*)/", $status, $matches
            )) {
                $duedate = $this->parseDate($matches[1]);
            } else {
                $duedate = null;
            }
            // process duedate
            if ($availability) {
                if ($this->duedates) {
                    foreach ($this->duedates as $key => $value) {
                        if (preg_match($value, $item_status['desc'])) {
                            $duedate = $key;
                            break;
                        }
                    }
                } else {
                    $duedate = $item_status['desc'];
                }
            } else {
                if ($status == "On Hold" || $status == "Requested") {
                    $duedate = "requested";
                }
            }

            $isKnav = (($sub_library_code == 'KNAV') || $sub_library_code == 'KNAVD') ? true : false;
            $requestability = $isKnav ? (string) $item_status['request'] : 'N';

            if ($historical_collection) {
              $requestability = 'N';
              $item_status['desc'] = (string) $z30->{'z30-item-status'};
            }

            $item_id = $item->attributes()->href;
            $item_id = substr($item_id, strrpos($item_id, '/') + 1);
            $note    = (string) $z30->{'z30-note-opac'};
            $holding[] = [
                'id'                => $id,
                'item_id'           => $item_id,
                'availability'      => $availability,
                'status'            => (string) $item_status['desc'],
                'avail_status'      => (string) $status,
                'location'          => $sub_library_code,
                'locationText'		=> $sub_library,
                'reserve'           => 'N',
                'callnumber'        => (string) $z30->{'z30-call-no'},
                'duedate'           => (string) $duedate,
                'number'            => (string) $z30->{'z30-inventory-number'},
                'barcode'           => (string) $z30->{'z30-barcode'},
                'description'       => (string) $z30->{'z30-description'},
                'notes'             => ($note == null) ? null : [$note],
                'is_holdable'       => $requestability!='N'?true:false,
                'addLink'           => $addLink,
                'holdtype'          => 'hold',
                /* below are optional attributes*/
                'collection'        => (string) $collection,
                'collection_desc'   => (string) $collection_desc['desc'],
                'callnumber_second' => (string) $z30->{'z30-call-no-2'},
                'sub_lib_desc'      => (string) $item_status['sub_lib_desc'],
                'no_of_loans'       => (string) $z30->{'$no_of_loans'},
                'requested'         => (string) $requested,
                'requestable'       => $requestability,
                'historical_collection' => $historical_collection,
            ];
        }
        dump($holding);
        return $holding;
    }

    public function parseDate($date)
    {
        if ($date == null || $date == "" || $date == 0) {
            return "";
        } else if (preg_match("/^[0-9]{8}$/", $date) === 1) { // 20120725
            return $this->dateConverter->convert('Ymd', 'd.m.Y', $date);
        } else if (preg_match("/^[0-9]+\/[A-Za-z]{3}\/[0-9]{4}$/", $date) === 1) {
            // 13/jan/2012
            return $this->dateConverter->convert('d/M/Y', 'd.m.Y', $date);
        } else if (preg_match("/^[0-9]+\/[0-9]+\/[0-9]{4}$/", $date) === 1) {
            // 13/7/2012
            return $this->dateConverter->convert('d/m/Y', 'd.m.Y', $date);
        } else {
            throw new \Exception("Invalid date: $date");
        }
    }

}

