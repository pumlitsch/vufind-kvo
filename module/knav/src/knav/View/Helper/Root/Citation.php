<?php

namespace knav\View\Helper\Root;
use VuFind\Exception\Date as DateException;

/**
 * Citation view helper
 */
class Citation extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Citation details
     *
     * @var array
     */
    protected $details = [];

    /**
     * Record driver
     *
     * @var \VuFind\RecordDriver\AbstractBase
     */
    protected $driver;

    /**
     * Date converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $converter Date converter
     */
    public function __construct(\VuFind\Date\Converter $converter)
    {
        $this->dateConverter = $converter;
    }

    /**
     * Store a record driver object and return this object so that the appropriate
     * template can be rendered.
     *
     * @param \VuFind\RecordDriver\Base $driver Record driver object.
     *
     * @return Citation
     */
    public function __invoke($driver)
    {
        // Build author list:
        $authors = [];
        $primary = $driver->tryMethod('getPrimaryAuthor');
        if (empty($primary)) {
            $primary = $driver->tryMethod('getCorporateAuthor');
        }
        if (!empty($primary)) {
            $authors[] = $primary;
        }
        $secondary = $driver->tryMethod('getSecondaryAuthors');
        if (is_array($secondary) && !empty($secondary)) {
            $authors = array_unique(array_merge($authors, $secondary));
        }

        // Get best available title details:
        $title = $driver->tryMethod('getShortTitle');
        $subtitle = $driver->tryMethod('getSubtitle');
        if (empty($title)) {
            $title = $driver->tryMethod('getTitle');
        }
        if (empty($title)) {
            $title = $driver->getBreadcrumb();
        }
        // Find subtitle in title if they're not separated:
        if (empty($subtitle) && strstr($title, ':')) {
            list($title, $subtitle) = explode(':', $title, 2);
        }

        // Extract the additional details from the record driver:
        $publishers = $driver->tryMethod('getPublishers');
        $pubDates = $driver->tryMethod('getPublicationDates');
        $pubPlaces = $driver->tryMethod('getPlacesOfPublication');
        $edition = $driver->tryMethod('getEdition');

        // Store everything:
        $this->driver = $driver;
        $this->details = [
            'authors' => $this->prepareAuthors($authors),
            'title' => trim($title), 'subtitle' => trim($subtitle),
            'pubPlace' => isset($pubPlaces[0]) ? $pubPlaces[0] : null,
            'pubName' => isset($publishers[0]) ? $publishers[0] : null,
            'pubDate' => isset($pubDates[0]) ? $pubDates[0] : null,
            'edition' => empty($edition) ? [] : [$edition],
            'journal' => $driver->tryMethod('getContainerTitle')
        ];

        return $this;
    }

    /**
     * The code in this module expects authors in "Last Name, First Name" format.
     *
     * @param array $authors Authors to process.
     *
     * @return array
     */
    protected function prepareAuthors($authors)
    {
        if (is_a($this->driver, 'VuFind\RecordDriver\SolrMarc')) {
            return $authors;
        }

        $processed = [];
        foreach ($authors as $name) {
            if (!strstr($name, ',')) {
                $parts = explode(' ', $name);
                if (count($parts) > 1) {
                    $last = array_pop($parts);
                    $first = implode(' ', $parts);
                    $name = $last . ', ' . $first;
                }
            }
            $processed[] = $name;
        }
        return $processed;
    }

    /**
     * Retrieve a citation in a particular format
     *
     * Returns the citation in the format specified
     *
     * @param string $format Citation format ('APA' or 'MLA')
     *
     * @return string        Formatted citation
     */
    public function getCitation($format)
    {
        $method = 'getCitation' . $format;

        // Avoid calls to inappropriate/missing methods:
        if (!empty($format) && method_exists($this, $method)) {
            return $this->$method();
        }

        return '';
    }

    /**
     * Get APA citation.
     *
     * This function assigns all the necessary variables and then returns an APA
     * citation.
     *
     * @return string
     */
    public function getCitationAPA()
    {
        $apa = [
            'title' => $this->getAPATitle(),
            'authors' => $this->getAPAAuthors(),
            'edition' => $this->getEdition()
        ];

        $apa['periodAfterTitle']
            = (!$this->isPunctuated($apa['title']) && empty($apa['edition']));

        // Behave differently for books vs. journals:
        $partial = $this->getView()->plugin('partial');
        if (empty($this->details['journal'])) {
            $apa['publisher'] = $this->getPublisher();
            $apa['year'] = $this->getYear();
            return $partial('Citation/apa.phtml', $apa);
        } else {
            list($apa['volume'], $apa['issue'], $apa['date'])
                = $this->getAPANumbersAndDate();
            $apa['journal'] = $this->details['journal'];
            $apa['pageRange'] = $this->getPageRange();
            if ($doi = $this->driver->tryMethod('getCleanDOI')) {
                $apa['doi'] = $doi;
            }
            return $partial('Citation/apa-article.phtml', $apa);
        }
    }


    /**
     * Get MLA citation.
     *
     * @return string
     */
    public function getCitationMLA($etAlThreshold = 4, $volNumSeparator = '.')
    {
        $mla = [
            'title' => $this->getMLATitle(),
            'authors' => $this->getMLAAuthors($etAlThreshold)
        ];
        $mla['periodAfterTitle'] = !$this->isPunctuated($mla['title']);

        // Behave differently for books vs. journals:
        $partial = $this->getView()->plugin('partial');
        if (empty($this->details['journal'])) {
            $mla['publisher'] = $this->getPublisher();
            $mla['year'] = $this->getYear();
            $mla['edition'] = $this->getEdition();
            return $partial('Citation/mla.phtml', $mla);
        } else {
            $mla['pageRange'] = $this->getPageRange();
            $mla['journal'] =  $this->capitalizeTitle($this->details['journal']);
            $mla['numberAndDate'] = $this->getMLANumberAndDate($volNumSeparator);
            return $partial('Citation/mla-article.phtml', $mla);
        }
    }

    /**
     * Construct page range portion of citation.
     *
     * @return string
     */
    protected function getPageRange()
    {
        $start = $this->driver->tryMethod('getContainerStartPage');
        $end = $this->driver->tryMethod('getContainerEndPage');
        return ($start == $end || empty($end))
            ? $start : $start . '-' . $end;
    }

    /**
     * Construct volume/issue/date portion of APA citation.  Returns an array with
     * three elements: volume, issue and date 
     *
     * @return array
     */
    protected function getAPANumbersAndDate()
    {
        $vol = $this->driver->tryMethod('getContainerVolume');
        $num = $this->driver->tryMethod('getContainerIssue');
        $date = $this->details['pubDate'];
        if (strlen($date) > 4) {
            try {
                $year = $this->dateConverter->convertFromDisplayDate('Y', $date);
                $month = $this->dateConverter->convertFromDisplayDate('F', $date);
                $day = $this->dateConverter->convertFromDisplayDate('j', $date);
            } catch (DateException $e) {
                $year = $date;
                $month = $day = '';
            }
        } else {
            $year = $date;
            $month = $day = '';
        }

        if (!empty($vol) || !empty($num)) {
            if (empty($vol) && !empty($num)) {
                $vol = $num;
                $num = '';
            }
            return [$vol, $num, $year];
        } else {
            $finalDate = $year
                . (empty($month) ? '' : ', ' . $month)
                . (($day > 1) ? ' ' . $day : '');
            return ['', '', $finalDate];
        }
    }

    /**
     * Is the string a valid name suffix?
     *
     * @param string $str The string to check.
     *
     * @return bool       True if it's a name suffix.
     */
    protected function isNameSuffix($str)
    {
        $str = $this->stripPunctuation($str);

        $suffixes = ['Jr', 'Sr'];
        if (in_array($str, $suffixes)) {
            return true;
        }

        // Is it a roman numeral? 
        if (preg_match('/^[MDCLXVI]+$/', $str)) {
            return true;
        }

        return false;
    }

    /**
     * Is the string a date range?
     *
     * @param string $str The string to check.
     *
     * @return bool       True if it's a date range.
     */
    protected function isDateRange($str)
    {
        $str = trim($str);
        return preg_match('/^([0-9]+)-([0-9]*)\.?$/', $str);
    }

    /**
     * Abbreviate a first name.
     *
     * @param string $name The name to abbreviate
     *
     * @return string      The abbreviated name.
     */
    protected function abbreviateName($name)
    {
        $parts = explode(', ', $name);
        $name = $parts[0];

        if (isset($parts[1]) && !$this->isDateRange($parts[1])) {
            $fnameParts = explode(' ', $parts[1]);
            for ($i = 0; $i < count($fnameParts); $i++) {
                if (function_exists('mb_substr')) {
                    $fnameParts[$i] = mb_substr($fnameParts[$i], 0, 1, 'utf8') . '.';
                } else {
                    $fnameParts[$i] = substr($fnameParts[$i], 0, 1) . '.';
                }
            }
            $name .= ', ' . implode(' ', $fnameParts);
            if (isset($parts[2]) && $this->isNameSuffix($parts[2])) {
                $name = trim($name) . ', ' . $parts[2];
            }
        }

        return trim($name);
    }

    /**
     * Fix bad punctuation on abbreviated name letters.
     *
     * @param string $str String to fix.
     *
     * @return string
     */
    protected function fixAbbreviatedNameLetters($str)
    {
        if (strlen($str) == 1
            || preg_match('/\s[a-zA-Z]/', substr($str, -2))
        ) {
            return $str . '.';
        }
        return $str;
    }

    /**
     * Strip the dates off the end of a name.
     *
     * @param string $str Name to clean.
     *
     * @return string     Cleaned name.
     */
    protected function cleanNameDates($str)
    {
        $arr = explode(', ', $str);
        $name = $arr[0];
        if (isset($arr[1]) && !$this->isDateRange($arr[1])) {
            $name .= ', ' . $this->fixAbbreviatedNameLetters($arr[1]);
            if (isset($arr[2]) && $this->isNameSuffix($arr[2])) {
                $name .= ', ' . $arr[2];
            }
        }
        return $name;
    }

    /**
     * Does the string end in punctuation that we want to retain?
     *
     * @param string $string String to test.
     *
     * @return boolean       Does string end in punctuation?
     */
    protected function isPunctuated($string)
    {
        $punctuation = ['.', '?', '!'];
        return (in_array(substr($string, -1), $punctuation));
    }

    /**
     * Strip unwanted punctuation from the right side of a string.
     *
     * @param string $text Text to clean up.
     *
     * @return string      Cleaned up text.
     */
    protected function stripPunctuation($text)
    {
        $punctuation = ['.', ',', ':', ';', '/'];
        $text = trim($text);
        if (in_array(substr($text, -1), $punctuation)) {
            $text = substr($text, 0, -1);
        }
        return trim($text);
    }

    /**
     * Turn a "Last, First" name into a "First Last" name.
     *
     * @param string $str Name to reverse.
     *
     * @return string     Reversed name.
     */
    protected function reverseName($str)
    {
        $arr = explode(', ', $str);

        // If the second chunk is a date range, there is nothing to reverse!
        if (!isset($arr[1]) || $this->isDateRange($arr[1])) {
            return $arr[0];
        }

        $name = $this->fixAbbreviatedNameLetters($arr[1]) . ' ' . $arr[0];
        if (isset($arr[2]) && $this->isNameSuffix($arr[2])) {
            $name .= ', ' . $arr[2];
        }
        return $name;
    }

    /**
     * Capitalize all words in a title, except for a few common exceptions.
     *
     * @param string $str Title to capitalize.
     *
     * @return string     Capitalized title.
     */
    protected function capitalizeTitle($str)
    {
        $exceptions = ['a', 'an', 'the', 'against', 'between', 'in', 'of',
            'to', 'and', 'but', 'for', 'nor', 'or', 'so', 'yet', 'to'];

        $words = explode(' ', $str);
        $newwords = [];
        $followsColon = false;
        foreach ($words as $word) {
            // Capitalize words unless they are in the exception list
            if (!in_array($word, $exceptions) || $followsColon) {
                $word = ucfirst($word);
            }
            array_push($newwords, $word);

            $followsColon = substr($word, -1) == ':';
        }

        return ucfirst(join(' ', $newwords));
    }

    /**
     * Get the full title for an APA citation.
     *
     * @return string
     */
    protected function getAPATitle()
    {
        $title = $this->stripPunctuation($this->details['title']);
        if (isset($this->details['subtitle'])) {
            $subtitle = $this->stripPunctuation($this->details['subtitle']);
            if (!empty($subtitle)) {
                $subtitle
                    = strtoupper(substr($subtitle, 0, 1)) . substr($subtitle, 1);
                $title .= ': ' . $subtitle;
            }
        }

        return $title;
    }

    /**
     * Get an array of authors for an APA citation.
     *
     * @return array
     */
    protected function getAPAAuthors()
    {
        $authorStr = '';
        if (isset($this->details['authors'])
            && is_array($this->details['authors'])
        ) {
            $i = 0;
            $ellipsis = false;
            foreach ($this->details['authors'] as $author) {
                $author = $this->abbreviateName($author);
                if (($i + 1 == count($this->details['authors']))
                    && ($i > 0)
                ) { 
                    $authorStr .= $ellipsis ? ' ' : '& ';
                    $authorStr .= $this->stripPunctuation($author) . '.';
                } elseif ($i > 5) {
                    if (!$ellipsis) {
                        $authorStr .= '. . .';
                        $ellipsis = true;
                    }
                } elseif (count($this->details['authors']) > 1) {
                    $authorStr .= $author . ', ';
                } else { 
                    $authorStr .= $this->stripPunctuation($author) . '.';
                }
                $i++;
            }
        }
        return (empty($authorStr) ? false : $authorStr);
    }

    /**
     * Get edition statement for inclusion in a citation. 
     *
     * @return string
     */
    protected function getEdition()
    {
        if (isset($this->details['edition'])
            && is_array($this->details['edition'])
        ) {
            foreach ($this->details['edition'] as $edition) {
                $edition = $this->stripPunctuation($edition);
                if (empty($edition)) {
                    continue;
                }
                if (!$this->isPunctuated($edition)) {
                    $edition .= '.';
                }
                if ($edition !== '1st ed.') {
                    return $edition;
                }
            }
        }

        return false;
    }

    /**
     * Get the full title for an MLA citation.
     *
     * @return string
     */
    protected function getMLATitle()
    {
        return $this->capitalizeTitle($this->getAPATitle());
    }

    /**
     * Get an array of authors for an MLA citation.
     */
    protected function getMLAAuthors($etAlThreshold = 4)
    {
        $authorStr = '';
        if (isset($this->details['authors'])
            && is_array($this->details['authors'])
        ) {
            $i = 0;
            if (count($this->details['authors']) > $etAlThreshold) {
                $author = $this->details['authors'][0];
                $authorStr = $this->cleanNameDates($author) . ', et al';
            } else {
                foreach ($this->details['authors'] as $author) {
                    if (($i + 1 == count($this->details['authors'])) && ($i > 0)) {
                        // Last
                        $authorStr .= ', and ' .
                            $this->reverseName($this->stripPunctuation($author));
                    } elseif ($i > 0) {
                        $authorStr .= ', ' .
                            $this->reverseName($this->stripPunctuation($author));
                    } else {
                        // First
                        $authorStr .= $this->cleanNameDates($author);
                    }
                    $i++;
                }
            }
        }
        return (empty($authorStr) ? false : $this->stripPunctuation($authorStr));
    }

    /**
     * Get publisher information (place: name) for inclusion in a citation.
     *
     * @return string
     */
    protected function getPublisher()
    {
        $parts = [];
        if (isset($this->details['pubPlace'])
            && !empty($this->details['pubPlace'])
        ) {
            $parts[] = $this->stripPunctuation($this->details['pubPlace']);
        }
        if (isset($this->details['pubName'])
            && !empty($this->details['pubName'])
        ) {
            $parts[] = $this->details['pubName'];
        }
        if (empty($parts)) {
            return false;
        }
        return $this->stripPunctuation(implode(': ', $parts));
    }

    /**
     * Get the year of publication for inclusion in a citation.
     *
     * @return string
     */
    protected function getYear()
    {
        if (isset($this->details['pubDate'])) {
            if (strlen($this->details['pubDate']) > 4) {
                try {
                    return $this->dateConverter->convertFromDisplayDate(
                        'Y', $this->details['pubDate']
                    );
                } catch (\Exception $e) {
                    // Ignore date errors
                    return false;
                }
            }
            return preg_replace('/[^0-9]/', '', $this->details['pubDate']);
        }
        return false;
    }
}
