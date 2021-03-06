<?php

require_once(mnminclude.'Zend/Search/Lucene.php');

// Configurable parameters
global $lucene_stopwords;
$lucene_stopwords = array('a', 'al', 'an', 'at', 'the', 'and', 'or', 'is', 'am', 'my', 'ho', 'la', 'el', 'las', 'los', 'de', 'me', 'mi', 'no', 'da',
		'ni', 'en', 'con', 'ha', 'he', 'es', 'un', 'una', 'tu', 'te', 'con', 'por', 'su', 'se', 'si', 'que', 'ya', 'yo', 'va', 'para', 'sin',
		'http', 'https', 'www', 'fue', 'le', 'del', 'lo', 'sus', 'ip', 'com');

function lucene_open() {
	global $globals, $lucene_stopwords;

	// Change the token analyzer
	$analyzer = new Mnm_Lucene_Analysis_Analyzer_Common_Utf8Num($lucene_stopwords);
	Zend_Search_Lucene_Analysis_Analyzer::setDefault($analyzer);

	if (file_exists($globals['lucene_dir'] )) {
		$index = Zend_Search_Lucene::open($globals['lucene_dir'] );
  	} else {
		print "Creando dir\n";
		$index = Zend_Search_Lucene::create($globals['lucene_dir']);
		@chmod($globals['lucene_dir'], 0777);
	}
	return $index;
}



function lucene_get_search_link_ids($by_date = false, $start = 0, $count = 50) {
	global $globals;

	$ids = array();

	if(!empty($_REQUEST['q'])) {
		if ($_REQUEST['p']) {
			//Allows variable "p" as prefix too
			$_REQUEST['q'] = $_REQUEST['p'].':'.$_REQUEST['q'];
		}

		$words = $_REQUEST['q'] = trim(substr(strip_tags($_REQUEST['q']), 0, 250)); 

		// Basic filtering to avoid Lucene errors
		$words = preg_replace('/\^([^1-9])/','$1',$words); // Only allow ^ followed by numbers
		//$words = preg_replace('/(.*) +(\w{1,2} *$)/', '$2 $1', $words); // Lucene dies if the last word has 2 chars, move it to the begining
		//$words = preg_replace('/[\~\*\(\)\[\]\|\{\}]/',' ',$words);
		//$words = preg_replace('/^ *(and|not|no|or|\&) *$/','',$words);

		if(preg_match('/^ *(\w+): *(.*)/', mb_strtolower($words), $matches)) {
			$prefix = $matches[1];
			$words = $matches[2];
		}
		if (preg_match('/^http[s]*/', $prefix)) { // It's an url search
			$words = "$prefix:$words";
			$prefix = false;
			$field = 'url';
		}
		$words_count = count(explode(" ", $words));
		if ($words_count == 1 || $prefix == 'date') {
			$words = preg_replace('/(^| )(\w)/', '+$2', $words);
			$by_date = true;
		}
		if ($prefix) {
			switch ($prefix) {
				case 'url';
					$field = 'url';
					break;
				case 'title';
					$field = 'title';
					break;
				case 'tag':
				case 'tags':
					$field = 'tags';
					break;
			}
		}
		// if there is only a word and is a number, do not search in urls
		if ($words_count == 1 && !$field && preg_match('/^\+*[0-9]{1,4}$/', $words)) {
			//$words = preg_replace('/\+/', '', $words);
			$words = "title:$words tags:$words content:$words";
		}
		if ($field) {
			$query = "$field:($words)";
		} else {
			$query = $words;
		}
		if (empty($query)) return false;

		if ($globals['bot']) {
			Zend_Search_Lucene::setResultSetLimit(40);
		} elseif ($words_count == 1) {
			Zend_Search_Lucene::setResultSetLimit(6000);
		} else {
			Zend_Search_Lucene::setResultSetLimit(3000);
		}
		$index = lucene_open();
		echo "<!--Query: $query -->\n";
		$hits = lucene_search($index, $query, $by_date);
		if (!$by_date && count($hits) > 200 && $words_count > 1 && !preg_match('/[\+\-\"]|(^|[ :])(AND|OR|NOT|TO) /i', $words)) {
			Zend_Search_Lucene::setResultSetLimit(200);
			$query = preg_replace('/(^|[: ]+)(\w)/', '$1+$2', $query);
			echo "\n<!--  Trying to refine with a new query: $query -->\n";
			$hits2 = lucene_search($index, $query, $by_date);
			if (count($hits2) > 0) {
				$base_hits = 0;
				$nhits2 = min(count($hits2), 10);
				// Add the second hits if they are not already among the fist 10
				for ($i=$nhits2-1; $i>=0;$i--) {
					$found = false;
					for ($j=$base_hits; $j<$base_hits+10 && ! $found; $j++) {
						if ($hits2[$i]->link_id == $hits[$j]->link_id) {
							//$hits[$j] = false;
							unset($hits[$j]);
							$found = true;
						}
					}
					array_unshift($hits, $hits2[$i]);
					$base_hits++;
				}
				//$hits = array_merge($hits2, $hits);
			}
		}
		$globals['rows'] = count($hits); // Save the number of hits
		$elements = min($globals['rows'], $start+$count);
		if ($elements == 0 || $elements < $start) return false;
		$previous = 0;
		$i=$start;
		for ($i=$start; $i<$elements; $i++) {
			$hit = $hits[$i];
			if ($hit && $hit->link_id != $previous) {
				array_push($ids, $hit->link_id);
				$previous = $hit->link_id;
			}
		}
		return $ids;
	} 
	return false;
}

function lucene_search($index, $query, $by_date) {
	try {
		if ($by_date) {
			return $index->find($query, 'date', SORT_NUMERIC, SORT_DESC);
		} else {
			return $index->find($query);
		}
	} catch (Zend_Search_Lucene_Search_QueryParserException $e) {
		//echo '<strong>'. _('consulta errónea') . '</strong>: ' . $_REQUEST['q']. ' (<em>'.$e->getMessage() . "</em>)\n";
		$_REQUEST['q'] = false;
		return false;
	}
}

// Following code is base on Zend Framework examples
/**
 * Zend Framework
 * @subpackage Analysis
 * @copyright  Copyright (c) 2005-2007 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */


require_once 'Zend/Search/Lucene/Analysis/Analyzer/Common.php';
require_once 'Zend/Search/Lucene/Analysis/TokenFilter.php';


class Mnm_Lucene_Analysis_Analyzer_Common_Utf8Num extends Zend_Search_Lucene_Analysis_Analyzer_Common
{
    /**
     * Current char position in an UTF-8 stream
     *
     * @var integer
     */
    private $_position;

    /**
     * Current binary position in an UTF-8 stream
     *
     * @var integer
     */
    private $_bytePosition;

    /**
     * Stream length
     *
     * @var integer
     */
    private $_streamLength;

    /**
     * Reset token stream
     */
    public function reset()
    {
        $this->_position     = 0;
        $this->_bytePosition = 0;

        // convert input into UTF-8
        $this->_encoding = 'UTF-8';

        // Get UTF-8 string length.
        // It also checks if it's a correct utf-8 string
		$this->_input = iconv("utf-8", "us-ascii//TRANSLIT", $this->_input);
        //$this->_streamLength = mb_strlen($this->_input);
        $this->_streamLength = strlen($this->_input);
    }

    /**
     * Check, that character is a letter
     *
     * @param string $char
     * @return boolean
     */
    private static function _isAlNum($char)
    {
        if (strlen($char) > 1) {
            // It's an UTF-8 character
			// check allowed chars
			return true;
        	//return ctype_alnum(iconv("utf-8", "us-ascii//TRANSLIT",$char));
        }
        return ctype_alnum($char);
    }

    /**
     * Get next UTF-8 char
     *
     * @param string $char
     * @return boolean
     */
    private function _nextChar()
    {
        $char = $this->_input[$this->_bytePosition++];

        if (( ord($char) & 0xC0 ) == 0xC0) {
            $addBytes = 1;
            if (ord($char) & 0x20 ) {
                $addBytes++;
                if (ord($char) & 0x10 ) {
                    $addBytes++;
                }
            }
            $char .= substr($this->_input, $this->_bytePosition, $addBytes);
            $this->_bytePosition += $addBytes;
        }

        $this->_position++;

        return $char;
    }

    /**
     * Tokenization stream API
     * Get next token
     * Returns null at the end of stream
     *
     * @return Zend_Search_Lucene_Analysis_Token|null
     */
    public function nextToken()
    {
        if ($this->_input === null) {
            return null;
        }

        while ($this->_position < $this->_streamLength) {
            // skip white space
            while ($this->_position < $this->_streamLength &&
                   !self::_isAlNum($char = $this->_nextChar())) {
                $char = '';
            }

            $termStartPosition = $this->_position - 1;
            $termText = $char;

            // read token
            while ($this->_position < $this->_streamLength &&
                   self::_isAlNum($char = $this->_nextChar())) {
                $termText .= $char;
            }

            // Empty token, end of stream.
            if ($termText == '') {
                return null;
            }

            $token = new Zend_Search_Lucene_Analysis_Token(
                                      $termText,
                                      $termStartPosition,
                                      $this->_position - 1);
            $token = $this->normalize($token);
            if ($token !== null) {
                return $token;
            }
            // Continue if token is skipped
        }

        return null;
    }
    public function __construct($stopwords = array())
    {
        $this->addFilter(new Mnm_Lucene_Analysis_TokenFilter_LowerCase($stopwords));
    }

}


class Mnm_Lucene_Analysis_TokenFilter_LowerCase extends Zend_Search_Lucene_Analysis_TokenFilter
{
	private $_stopSet;
    /**
     * Normalize Token or remove it (if null is returned)
     *
     * @param Zend_Search_Lucene_Analysis_Token $srcToken
     * @return Zend_Search_Lucene_Analysis_Token
     */
    public function normalize(Zend_Search_Lucene_Analysis_Token $srcToken)
    {
		//iconv("utf-8", "us-ascii//TRANSLIT", $url); // TRANSLIT does the whole job
		// We could use also remove_accents() in uri.php
		// Problem: ñ -> n
		//$token = strtolower(iconv("utf-8", "us-ascii//TRANSLIT", $srcToken->getTermText()));
		$token = strtolower($srcToken->getTermText());
		if (strlen($token) < 2  || 
			array_key_exists($token, $this->_stopSet) ) {
			return null;
		}

        $newToken = new Zend_Search_Lucene_Analysis_Token(
                                     $token,
                                     $srcToken->getStartOffset(),
                                     $srcToken->getEndOffset());
        $newToken->setPositionIncrement($srcToken->getPositionIncrement());

        return $newToken;
    }

	public function __construct($stopwords = array()) {
		$this->_stopSet = array_flip($stopwords);
	}

}
?>
