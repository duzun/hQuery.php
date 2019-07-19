<?php

namespace duzun;

// ------------------------------------------------------------------------
use duzun\hQuery\Parser\HTML as HTMLParser;
use duzun\hQuery\Parser\Selector as SelectorParser;

// ------------------------------------------------------------------------
use Http\Discovery\HttpClientDiscovery;
// use Psr\Http\Message\ResponseInterface;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;

// ------------------------------------------------------------------------
class_exists('duzun\\hQuery\\HTML_Index', false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'hQuery' . DIRECTORY_SEPARATOR . 'HTML_Index.php';

// ------------------------------------------------------------------------
/**
 *  Main Class, represents an HTML document.
 *
 *  An extremely fast web scraper that parses megabytes of HTML in a blink of an eye.
 *  PHP5.3+, no dependencies.
 *
 *  API Documentation at https://duzun.github.io/hQuery.php
 *
 *  Copyright (C) 2014-2018 Dumitru Uzun
 *
 *  @version 3.0.3
 *  @author Dumitru Uzun (DUzun.ME)
 *  @license MIT
 */
class hQuery extends hQuery\HTML_Index
{
    // ------------------------------------------------------------------------
    // Response headers when using self::fromURL()
    /**
     * @var mixed
     */
    public $headers;

    /**
     * Optional cache path to store HTTP responses (see fromURL())
     * @var string
     */
    public static $cache_path;

    /**
     * Cache expires in seconds. Only if $cache_path is set.
     * @var integer
     */
    public static $cache_expires = 3600;

    // ------------------------------------------------------------------------
    /**
     * @var mixed
     */
    public static $_mockup_class; // Used internally for teting

    // ------------------------------------------------------------------------
    /**
     *  Parse an HTML string.
     *
     * @param  string|Psr\Http\Message\MessageInterface $html  - source of some HTML document
     * @param  string                                   $url   - OPTIONAL location of the document. Used for relative URLs inside the document.
     * @return hQuery                                   $doc
     */
    public static function fromHTML($html, $url = null)
    {
        if ($html instanceof MessageInterface) {
            $message = $html;
            $html    = $message->getBody() . '';

            if (!isset($url) && $message instanceof RequestInterface) {
                $url = $message->getUri() . '';
            }
        }

        $index_time = microtime(true);
        if (isset(self::$_mockup_class)) {
            $doc = new self::$_mockup_class($html, false);
        } else {
            $doc = new self($html, false);
        }
        if ($url) {
            $doc->location($url);
        }
        if (!empty($message)) {
            $doc->message     = $message;
            $doc->headers     = $message->getHeaders();
            $doc->source_type = 'message';

            if (!empty(self::$last_http_result) && self::$last_http_result->response === $message) {
                self::$last_http_result->body = $html;
            }
        }
        $doc->index();
        $index_time      = microtime(true) - $index_time;
        $doc->index_time = $index_time * 1000;
        return $doc;
    }

    /**
     *  Read the HTML document from a file.
     *
     * @param  string   $filename         - a valid filename
     * @param  bool     $use_include_path - OPTIONAL passed to file_get_contents()
     * @param  resource $context          - OPTIONAL A valid context resource created with stream_context_create(). See file_get_contents()
     * @return hQuery   $doc
     */
    public static function fromFile($filename, $use_include_path = false, $context = null)
    {
        $read_time = microtime(true);
        $html      = file_get_contents($filename, $use_include_path, $context);
        $read_time = microtime(true) - $read_time;
        if (false === $html) {
            return $html;
        }

        $doc              = self::fromHTML($html, $filename);
        $doc->source_type = 'file';
        $doc->read_time   = $read_time * 1000;
        return $doc;
    }

    /**
     *  Fetch the HTML document from remote $url.
     *
     * @param  string       $url     - the URL of the document
     * @param  array        $headers - OPTIONAL request headers
     * @param  array|string $body    - OPTIONAL body of the request (for POST or PUT)
     * @param  array        $options - OPTIONAL request options (see self::http_wr() for more details)
     * @return hQuery       $doc for 200 response code, FALSE otherwise
     */
    public static function fromURL($url, $headers = null, $body = null, $options = null)
    {
        $opt = array(
            'timeout'   => 7,
            'redirects' => 7,
            'close'     => false,
            'decode'    => 'gzip',
            'expires'   => self::$cache_expires,
        );
        $hd = array('Accept-Charset' => 'UTF-8,*');

        if ($options) {
            $opt = $options + $opt;
        }

        if ($headers) {
            $hd = $headers + $hd;
        }

        $expires = $opt['expires'];
        unset($opt['expires']);

        if (0 < $expires and $dir = self::$cache_path) {
            ksort($opt);
            $t = realpath($dir) and $dir = $t or mkdir($dir, 0766, true);
            $dir .= DIRECTORY_SEPARATOR;
            $cch_id = hash('sha1', $url, true);
            $t      = hash('md5', self::jsonize($opt), true);
            $cch_id = bin2hex(substr($cch_id, 0, -strlen($t)) . (substr($cch_id, -strlen($t)) ^ $t));
            $cch_fn = $dir . $cch_id;
            $ext    = strtolower(strrchr($url, '.'));
            if (strlen($ext) < 7 && preg_match('/^\\.[a-z0-9]+$/', $ext)) {
                $cch_fn .= $ext;
            }
            $cch_fn .= '.gz';
            $read_time = microtime(true);
            $ret       = self::get_cache($cch_fn, $expires, false);
            $read_time = microtime(true) - $read_time;
            if ($ret) {
                $source_type            = 'cache';
                $html                   = $ret[0];
                $hdrs                   = $ret[1]['hdr'];
                $code                   = $ret[1]['code'];
                $url                    = $ret[1]['url'];
                $cch_meta               = $ret[1];
                self::$last_http_result = (object) array(
                    'body'    => $html,
                    'code'    => $code,
                    'url'     => $url,
                    'headers' => $hdrs,
                    'cached'  => true,
                );
            }
        } else {
            $ret = null;
        }

        if (empty($ret)) {
            $source_type = 'url';
            $read_time   = microtime(true);
            // var_export(compact('url', 'opt', 'hd'));
            $ret       = self::http_wr($url, $hd, $body, $opt);
            $read_time = microtime(true) - $read_time;
            $html      = $ret->body;
            $code      = $ret->code;
            $hdrs      = $ret->headers;

            // Catch the redirects
            if ($ret->url) {
                $url = $ret->url;
            }

            if (!empty($cch_fn)) {
                $save = self::set_cache($cch_fn, $html, array('hdr' => $hdrs, 'code' => $code, 'url' => $url));
            }
        }
        if (200 != $code) {
            return false;
        }

        $doc = self::fromHTML($html, $url);
        if ($doc) {
            $doc->headers                         = $hdrs;
            $doc->source_type                     = $source_type;
            isset($read_time) and $doc->read_time = $read_time * 1e3;
            if (!empty($cch_meta)) {
                $doc->cch_meta = $cch_meta;
            }
        }

        return $doc;
    }

    /**
     * Send a HTTP request and create a hQuery document from the response (PSR-7).
     *
     * @param  Psr\Http\Message\RequestInterface $request
     * @param  Http\Client\HttpClient            $client    An optional HTTP client. If missing, HttpClientDiscovery is used to create one.
     * @return hQuery                            $doc
     */
    public static function sendRequest(RequestInterface $request, $client = null)
    {
        // if ( !class_exists('Http\Discovery\HttpClientDiscovery') ) {
        //     throw new Exception('Http\Discovery\HttpClientDiscovery not found. Install a client (eg. `require php-http/curl-client`)');
        // }
        if (!isset($client)) {
            $client = self::$http_client;
        }
        /**
         * @var mixed
         */
        static $httpClient;
        if (!isset($client)) {
            if (!isset($httpClient)) {
                $httpClient = HttpClientDiscovery::find();
            }
            $client = $httpClient;
        }

        $read_time = microtime(true);
        $response  = $client->sendRequest($request);
        $read_time = microtime(true) - $read_time;

        $source_type = 'request';

        $code = $response->getStatusCode();
        $url  = $request->getUri() . '';

        self::$last_http_result = (object) array(
            'body'     => '', // to be set in fromHTML() - this avoids double call to __toString() on body
            'code'     => $code,
            'url'      => $url,
            'headers'  => $response->getHeaders(),
            // 'cached'  => false,
            'request'  => $request,
            'response' => $response,
        );

        if (200 != $code) {
            return false;
        }

        $doc = self::fromHTML($response, $url);
        if ($doc) {
            // Set $doc url if not set yet
            if (!$doc->baseURI() and $url) {
                $doc->location($url);
            }

            $doc->source_type                     = $source_type;
            isset($read_time) and $doc->read_time = $read_time * 1e3;
            // if(!empty($cch_meta)) $doc->cch_meta = $cch_meta;
        }

        return $doc;
    }

    // ------------------------------------------------------------------------
    /**
     *  Finds a collection of nodes inside current document/context (similar to jQuery.fn.find()).
     *
     * @param  string         $sel       - A valid CSS selector (some pseudo-selectors supported).
     * @param  array|string   $attr      - OPTIONAL attributes as string or key-value pairs.
     * @param  hQuery\Node    $ctx       - OPTIONAL the context where to search. If omitted, $this is used.
     * @return hQuery\Element collection of matched elements or NULL
     */
    public function find($sel, $_attr = null, $ctx = null)
    {
        $attr = array();
        $c    = func_num_args();
        for ($i = 1; $i < $c; ++$i) {
            $a = func_get_arg($i);
            if (is_object($a)) {
                if ($a instanceof hQuery\Node) {
                    $ctx = $a;
                } else {
                    throw new \Exception('Wrong context in ' . __METHOD__);
                }
            } elseif (is_array($a)) {
                $attr = array_merge($attr, $a);
            } elseif (is_string($a)) {
                $attr = array_merge($attr, HTMLParser::parseAttrStr($a));
            }
        }

        if (isset($ctx)) {
            $ctx = $this->_get_ctx($ctx);

            // Empty context yields empty result
            if (!$ctx) {
                return null;
            }
        }

        $sel = SelectorParser::exec(trim($sel));

        $ra = null;
        // , //
        foreach ($sel as $a) {
            $rb = null;
            $cx = $ctx;
            foreach ($a as $b) {
                if (empty($b['a'])) {
                    $at = $attr;
                } else {
                    $at = $b['a'];
                    if ($attr) {
                        $at += $attr;
                    }
                }
                if (isset($b['i'])) {
                    $at['id'] = $b['i'];
                }
                $n = isset($b['n']) ? $b['n'] : null;
                $c = isset($b['c']) ? $b['c'] : null;

                if (!$rb) {
                    $rb = $this->_find($n, $c, $at, $cx);
                } else {
                    $x = isset($b['x']) ? $b['x'] : ' ';
                    switch ($x) {
                        case ' ':
                            $cx = $this->_get_ctx($rb);
                            if ($cx) {
                                $rb = $this->_find($n, $c, $at, $cx);
                            }
                            // Empty context -> empty result
                            else {
                                $rb = null;
                            }
                            break;

                        case '>':
                            $ch = $this->_children($rb);

                            $rb = $this->_filter($ch, $n, $c, $at);
                            // $rb = $this->_find($n, $c, $at, $cx);
                            // $rb = array_intersect_key($rb, $ch);

                            break;

                        case '+':
                            $ch = $this->_next($rb);
                            $rb = $this->_filter($ch, $n, $c, $at);
                            break;

                        case '~':
                            $ch = $this->_next($rb, 0, -1);
                            $rb = $this->_filter($ch, $n, $c, $at);
                            break;
                    }
                }

                if (!$rb) {
                    break;
                }

                if (isset($b['p'])) {
                    foreach ($b['p'] as $p) {
                        if (is_int($p)) {
                            if ($p < 0) {
                                $p += count($rb);
                            }

                            if (count($rb) >= 1 || $p) {
                                $rb = $p < 0 ? null : array_slice($rb, $p, 1, true);
                            }
                        } elseif (is_array($p)) {
                            $ch = reset($p);
                            switch (key($p)) {
                                case '<':
                                    $rb = array_slice($rb, 0, $ch, true);
                                    break;
                                case '>':
                                    $rb = array_slice($rb, $ch, count($rb), true);
                                    break;
                                case '-':
                                    $rb = $this->_prev($rb, $ch);
                                    break;
                                case '+':
                                    $rb = $this->_next($rb, $ch);
                                    break;
                                case '|':
                                    do $rb = $this->_parent($rb);
                                    while ($ch-- > 0);
                                    break;
                                case '*':
                                    do $rb = $this->_children($rb);
                                    while ($ch-- > 0);
                                    break;
                            }
                        }
                        if (!$rb) {
                            break 2;
                        }
                    }
                }
            }
            if ($rb) {
                if (!$ra) {
                    $ra = $rb;
                } else {
                    $ra += $rb;
                    // foreach ($rb as $rb => $rc) {
                    //     $ra[$rb] = $rc;
                    // }
                }
            }
        }

        if ($ra) {
            ksort($ra);
            return new hQuery\Element($this, $ra);
        }
        return null;
    }

    /**
     *  Combination of ->find() + ->html()
     *
     * @param  string       $sel  - A valid CSS selector.
     * @param  array|string $attr - OPTIONAL attributes as string or key-value pairs.
     * @param  hQuery\Node  $ctx  - OPTIONAL the context where to search. If omitted, $this is used.
     * @return array        list of HTML contents of all matched elements
     */
    public function find_html($sel, $attr = null, $ctx = null)
    {
        $r   = $this->find($sel, $attr = null, $ctx = null);
        $ret = self::$_ar_;
        if ($r) {
            foreach ($r as $k => $v) {
                $ret[$k] = $v->html();
            }
        }

        return $ret;
    }

    /**
     *  Combination of ->find() + ->text()
     *
     * @param  string       $sel  - A valid CSS selector.
     * @param  array|string $attr - OPTIONAL attributes as string or key-value pairs.
     * @param  hQuery\Node  $ctx  - OPTIONAL the context where to search. If omitted, $this is used.
     * @return array        list of Text contents of all matched elements
     */
    public function find_text($sel, $attr = null, $ctx = null)
    {
        $r   = $this->find($sel, $attr = null, $ctx = null);
        $ret = self::$_ar_;
        if ($r) {
            foreach ($r as $k => $v) {
                $ret[$k] = $v->text();
            }
        }

        return $ret;
    }

    /**
     * Index elements of the source HTML. (Called automatically)
     */
    public function index()
    {
        return $this->_index_all();
    }

    // - Helpers ------------------------------------------------

    // ---------------------------------------------------------------
    /**
     *  Serialize $data as JSON, fallback to serialize.
     *
     * @param  mixed   $data - the data to be serialized
     * @param  &string $type - returns the serialization method used ('json' | 'ser')
     * @return string  the serialized data
     */
    public static function jsonize($data, &$type = null, $ops = 0)
    {
        if (defined('JSON_UNESCAPED_UNICODE')) {
            $ops |= JSON_UNESCAPED_UNICODE;
        }
        $str = $ops ? json_encode($data, $ops) : json_encode($data);
        if (false === $str/*&& json_last_error() != JSON_ERROR_NONE*/) {
            $str  = serialize($data);
            $type = 'ser';
        } else {
            $type = 'json';
        }
        return $str;
    }

    /**
     *  Unserialize $data from either JSON or serialize.
     *
     *                          if set, forces unjsonize() to use this method for unserialization.
     * @param  string  $str  - the data to be unserialized
     * @param  &string $type - if not set, returns the serialization method detected ('json' | 'ser');
     * @return mixed   the unserialized data
     */
    public static function unjsonize($str, &$type = null)
    {
        if (!isset($type)) {
            $type = self::serjstype($str);
        }
        /**
         * @var mixed
         */
        static $_json_support;
        if (!isset($_json_support)) {
            $_json_support = 0;
            // PHP 5 >= 5.3.0
            if (function_exists('json_last_error')) {
                ++$_json_support;
                // PHP 5 >= 5.5.0
                if (function_exists('json_last_error_msg')) {
                    ++$_json_support;
                }
            }
        }
        switch ($type) {
            case 'ser': {
                    $data = @unserialize($str);
                    if (false === $data) {
                        if (strpos($str, "\n") !== false) {
                            if ($retry = strpos($str, "\r") === false) {
                                $str = str_replace("\n", "\r\n", $str);
                            } elseif ($retry = strpos($str, "\r\n") !== false) {
                                $str = str_replace("\r\n", "\n", $str);
                            }
                            $retry and $data = unserialize($str);
                        }
                    }
                }
                break;

            case 'json': {
                    $data = json_decode($str, true);
                    // Check for errors only if $data is NULL
                    if (is_null($data)) {
                        // If can't decode JSON, try to remove trailing commans in arrays and objects:
                        if (0 == $_json_support ? 'null' !== $str : json_last_error() != JSON_ERROR_NONE) {
                            $t    = preg_replace('/,\s*([\]\}])/m', '$1', $str) and
                                $data = json_decode($t, true);
                        }
                        if (is_null($data)) {
                            // PHP 5 >= 5.3.0
                            if ($_json_support) {
                                if (json_last_error() != JSON_ERROR_NONE) {
                                    // PHP 5 >= 5.5.0
                                    if ($_json_support > 1) {
                                        error_log('json_decode: ' . json_last_error_msg());
                                    } elseif ($_json_support > 0) {
                                        error_log('json_decode error with code #' . json_last_error());
                                    }
                                }
                            }
                            // PHP 5 < 5.3.0
                            else {
                                // Only 'null' should result in NULL
                                if ('null' !== $str) {
                                    error_log('json_decode error');
                                }
                            }
                        }
                    }
                }
                break;

            default: {
                    // at least try!
                    $data = json_decode($str, true);
                    if (is_null($data) && (0 == $_json_support ? 'null' !== $str : json_last_error() != JSON_ERROR_NONE)) {
                        $data = unserialize($str);
                    }
                }
        }
        return $data;
    }

    /**
     *  Tries to detect format of $str (json or ser).
     *
     * @param  string $str   - JSON encoded or PHP serialized data.
     * @return string 'json' | 'ser', or FALSE on failure to detect format.
     */
    protected static function serjstype($str)
    {
        $c = substr($str, 0, 1);
        if ('N;' === $str || strpos('sibadO', $c) !== false && substr($str, 1, 1) === ':') {
            $type = 'ser';
        } else {
            $l = substr($str, -1);
            if ('{' == $c && '}' == $l || '[' == $c && ']' == $l) {
                $type = 'json';
            } else {
                $type = false; // Unknown
            }
        }
        return $type;
    }

    /**
     * Find a function to decode gzip data.
     * @return string A gzip decode function name, or false if not found
     */
    public static function gz_supported()
    {
        function_exists('zlib_decode') and $_gzdecode = 'zlib_decode' or
            function_exists('gzdecode') and $_gzdecode    = 'gzdecode' or
            $_gzdecode                                    = false;
        return $_gzdecode;
    }

    /**
     * gzdecode() (for PHP < 5.4.0)
     */
    public static function gzdecode($str)
    {
        /**
         * @var mixed
         */
        static $_gzdecode;
        if (!isset($_gzdecode)) {
            $_gzdecode = self::gz_supported();
        }

        return $_gzdecode ? $_gzdecode($str) : self::_gzdecode($str);
    }

    /**
     * Alternative gzdecode() (for PHP < 5.4.0)
     * source: https://github.com/Polycademy/upgradephp/blob/master/upgrade.php
     */
    protected static function _gzdecode($gzdata, $maxlen = null)
    {
        #-- decode header
        $len = strlen($gzdata);
        if ($len < 20) {
            return;
        }
        $head                                   = substr($gzdata, 0, 10);
        $head                                   = unpack('n1id/C1cm/C1flg/V1mtime/C1xfl/C1os', $head);
        list($ID, $CM, $FLG, $MTIME, $XFL, $OS) = array_values($head);
        $FTEXT                                  = 1 << 0;
        $FHCRC                                  = 1 << 1;
        $FEXTRA                                 = 1 << 2;
        $FNAME                                  = 1 << 3;
        $FCOMMENT                               = 1 << 4;
        $head                                   = unpack('V1crc/V1isize', substr($gzdata, $len - 8, 8));
        list($CRC32, $ISIZE)                    = array_values($head);

        #-- check gzip stream identifier
        if (0x1f8b != $ID) {
            trigger_error('gzdecode: not in gzip format', E_USER_WARNING);
            return;
        }
        #-- check for deflate algorithm
        if (8 != $CM) {
            trigger_error('gzdecode: cannot decode anything but deflated streams', E_USER_WARNING);
            return;
        }
        #-- start of data, skip bonus fields
        $s = 10;
        if ($FLG & $FEXTRA) {
            $s += $XFL;
        }
        if ($FLG & $FNAME) {
            $s = strpos($gzdata, "\000", $s) + 1;
        }
        if ($FLG & $FCOMMENT) {
            $s = strpos($gzdata, "\000", $s) + 1;
        }
        if ($FLG & $FHCRC) {
            $s += 2; // cannot check
        }

        #-- get data, uncompress
        $gzdata = substr($gzdata, $s, $len - $s);
        if ($maxlen) {
            $gzdata = gzinflate($gzdata, $maxlen);
            return ($gzdata); // no checks(?!)
        } else {
            $gzdata = gzinflate($gzdata);
        }

        #-- check+fin
        $chk = crc32($gzdata);
        if ($CRC32 != $chk) {
            trigger_error("gzdecode: checksum failed (real$chk != comp$CRC32)", E_USER_WARNING);
        } elseif (strlen($gzdata) != $ISIZE) {
            trigger_error('gzdecode: stream size mismatch', E_USER_WARNING);
        } else {
            return ($gzdata);
        }
    }

    /**
     *  Read data from a cache file.
     *
     * @param  string $fn        - cache filename
     * @param  int    $expire    - OPTIONAL contents returned only if it is newer then $expire seconds
     * @param  bool   $meta_only - OPTIONAL if TRUE, read only meta-info (faster)
     * @return array  [mixed <contents>, array <meta_info>]
     */
    protected static function get_cache($fn, $expire = false, $meta_only = false)
    {
        $meta = $cnt = null;
        if (file_exists($fn) and $fm = filemtime($fn) and (!$expire || $fm + $expire > time())) {
            $cnt = self::flock_get_contents($fn);
        }
        $t = strlen($cnt);
        if (!empty($cnt)) {
            if ($gz = !strncmp($cnt, "\x1F\x8B", 2)) {
                $cnt = self::gzdecode($cnt);
            }
            if ('#' == $cnt[0]) {
                $n = (int) substr($cnt, 1, 0x10);
                $l = strlen($n) + 2;
                if ($n) {
                    $meta = substr($cnt, $l, $n);
                    if ('' !== $meta) {
                        $meta = self::unjsonize($meta);
                    }
                }
                if ($meta_only) {
                    $cnt = '';
                } else {
                    $l += $n;
                    if ("\n" == $cnt[$l]) {
                        $cnt = substr($cnt, ++$l);
                        if ('' !== $cnt) {
                            $cnt = self::unjsonize($cnt);
                        }
                    } else {
                        $cnt = substr($cnt, $l);
                    }
                }
            } else {
                if ($meta_only) {
                    $cnt = '';
                }
            }
        }
        return $cnt || $meta ? array($cnt, $meta) : false;
    }

    /**
     *  Save data to a cache file.
     *
     * @param  string   $fn   - cache filename
     * @param  mixed    $cnt  - contents to be cached
     * @param  array    $meta - OPTIONAL meta information related to contents.
     * @param  bool     $gzip - OPTIONAL if TRUE and gzip supported, store contents gzipped
     * @return int|bool On success, number of written bytes, FALSE on fail.
     */
    protected static function set_cache($fn, $cnt, $meta = null, $gzip = true)
    {
        if (false === $cnt) {
            return !file_exists($fn) || unlink($fn);
        }

        $n = 0;
        if (isset($meta)) {
            $meta = self::jsonize($meta);
            $n += strlen($meta);
        }
        $meta = '#' . $n . "\n" . $meta;
        if (!is_string($cnt) || "\n" == $cnt[0]) {
            $cnt = "\n" . self::jsonize($cnt);
            ++$n;
        }
        if ($n) {
            $cnt = $meta . $cnt;
        }

        unset($meta);
        if ($gzip) {
            $gl = is_int($gzip) ? $gzip : 1024;
            // Cache as gzip only if built-in gzdecode() defined (more CPU for less IO)
            strlen($cnt) > $gl && self::gz_supported() and
                $cnt = gzencode($cnt);
        }
        file_exists($dn = dirname($fn)) or mkdir($dn, 0777, true);
        return self::flock_put_contents($fn, $cnt);
    }

    /**
     * Lock with retries
     *
     * @author Dumitru Uzun
     *
     * @param  resource $fp         - Open file pointer
     * @param  int      $lock       - Lock type
     * @param  int      $timeout_ms - OPTIONAL Timeout to wait for unlock in miliseconds
     * @return true     on success, false on fail
     */
    public static function do_flock($fp, $lock, $timeout_ms = 384)
    {
        $l = flock($fp, $lock);
        if (!$l && ($lock & LOCK_UN) != LOCK_UN) {
            $st = microtime(true);
            $m  = min(1e3, $timeout_ms * 1e3);
            $n  = min(64e3, $timeout_ms * 1e3);
            if ($m == $n) {
                $m = ($n >> 1) + 1;
            }

            $timeout_ms = (float) $timeout_ms / 1000;
            // If lock not obtained sleep for 0 - 64 milliseconds, to avoid collision and CPU load
            do {
                usleep($t = rand($m, $n));
                $l = flock($fp, $lock);
            } while (!$l && (microtime(true) - $st) < $timeout_ms);
        }
        return $l;
    }

    /**
     * @param  $fn
     * @param  $cnt
     * @param  $block
     * @return mixed
     */
    public static function flock_put_contents($fn, $cnt, $block = false)
    {
        // return file_put_contents($fn, $cnt, $block & FILE_APPEND);
        $ret = false;
        if ($f = fopen($fn, 'c+')) {
            $app = $block & FILE_APPEND and $block ^= $app;
            if ($block ? self::do_flock($f, LOCK_EX) : flock($f, LOCK_EX | LOCK_NB)) {
                if (is_array($cnt) || is_object($cnt)) {
                    $cnt = self::jsonize($cnt);
                }

                if ($app) {
                    fseek($f, 0, SEEK_END);
                }

                if (false !== ($ret = fwrite($f, $cnt))) {
                    fflush($f);
                    ftruncate($f, ftell($f));
                }
                flock($f, LOCK_UN);
            }
            fclose($f);
        }
        return $ret;
    }

    /**
     * @param  $fn
     * @param  $block
     * @return mixed
     */
    public static function flock_get_contents($fn, $block = false)
    {
        // return file_get_contents($fn);
        $ret = false;
        if ($f = fopen($fn, 'r')) {
            if (flock($f, LOCK_SH | ($block ? 0 : LOCK_NB))) {
                $s           = 1 << 14;
                do $ret .= $r = fread($f, $s);
                while (false !== $r && !feof($f));
                if (null == $ret && false === $r) {
                    $ret = $r;
                }

                // filesize result is cached
                flock($f, LOCK_UN);
            }
            fclose($f);
        }
        return $ret;
    }

    // ------------------------------------------------------------------------
    /**
     * @param  $str
     * @return mixed
     */
    public static function parse_cookie($str)
    {
        $ret = array();
        if (is_array($str)) {
            foreach ($str as $k => $v) {
                $ret[$k] = self::parse_cookie($v);
            }
            return $ret;
        }

        $str          = explode(';', $str);
        $t            = explode('=', array_shift($str), 2);
        $ret['key']   = $t[0];
        $ret['value'] = $t[1];
        foreach ($str as $t) {
            $t = explode('=', trim($t), 2);
            if (count($t) == 2) {
                $ret[strtolower($t[0])] = $t[1];
            } else {
                $ret[strtolower($t[0])] = true;
            }
        }

        if (!empty($ret['expires']) && is_string($ret['expires'])) {
            $t = strtotime($ret['expires']);
            if (false !== $t and -1 !== $t) {
                $ret['expires'] = $t;
            }
        }

        return $ret;
    }

    // ------------------------------------------------------------------------
    /**
     * Executes a HTTP write-read session.
     *
     *                              timeout - connection timeout in seconds
     *                              host    - goes to headers, overrides $host (ex. $host == '127.0.0.1', $options['host'] == 'www.example.com')
     *                              port    - usefull when $host is not a full URL
     *                              scheme  - http, ssl, tls, udp, ...
     *                              close   - whether to close connection o not
     *                              redirects - number of allowed redirects
     *                              redirect_method - if (string), this is the new method for redirect request, else
     *                                                if true, preserve method, else use 'GET' on redirect.
     *                                                by default preserve on 307 and 308, GET on 301-303
     * @author Dumitru Uzun
     *
     * @param  string $host      - IP/HOST address or URL
     * @param  array  $head      - list off HTTP headers to be sent along with the request to $host
     * @param  mixed  $body      - data to be sent as the contents of the request. If is array or object, a http query is built.
     * @param  array  $options   - list of option as key-value:
     * @return array  [contents, headers, http-status-code, http-status-message]
     */
    public static function http_wr($host, $head = null, $body = null, $options = null)
    {
        self::$last_http_result      =
            $ret                         = new \stdClass();
        empty($options) and $options = array();

        // If $host is a URL
        if ($p = strpos($host, '://') and $p < 7) {
            $ret->url = $host;
            $p        = parse_url($host);
            if (!$p) {
                throw new \Exception('Wrong host specified'); // error
            }
            $host = $p['host'];
            $path = isset($p['path']) ? $p['path'] : null;
            if (isset($p['query'])) {
                $path .= '?' . $p['query'];
            }
            if (isset($p['port'])) {
                $port = $p['port'];
            }
            unset($p['path'], $p['query']);
            $options += $p;
        }
        // If $host is not an URL, but might contain path and port
        else {
            $p = explode('/', $host, 2);
            list($host, $path) = $p;
            $p = explode(':', $host, 2);
            list($host, $port) = $p;
        }

        if (strncmp($path, '/', 1)) {
            $path = '/' . $path;
        }
        // isset($path) or $path = '/';

        if (!isset($port)) {
            if (isset($options['port'])) {
                $port = $options['port'];
            } else {
                switch ($options['scheme']) {
                    case 'tls':
                    case 'ssl':
                    case 'https':
                        $port = 443;
                        break;
                    case 'ftp':
                        $port = 21;
                        break;
                    case 'sftp':
                        $port = 22;
                        break;
                    case 'http':
                    default:
                        $port = 80;
                }
            }
        }

        $ret->host =
            $conhost   = $host;
        $_h        = array(
            'host'   => isset($options['host']) ? $options['host'] : $host,
            'accept' => 'text/html,application/xhtml+xml,application/xml;q =0.9,*/*;q=0.8',
        );
        if (!empty($options['scheme'])) {
            switch ($p['scheme']) {
                case 'http':
                case 'ftp':
                    break;
                case 'https':
                    $conhost = 'tls://' . $host;
                    break;
                default:
                    $conhost = $options['scheme'] . '://' . $host;
            }
        }

        /**
         * @var string
         */
        static $boundary = "\r\n\r\n";
        $blen            = strlen($boundary);
        if ($body) {
            if (is_array($body) || is_object($body)) {
                $body               = http_build_query($body);
                $_h['content-type'] = 'application/x-www-form-urlencoded';
            }
            $body                 = (string) $body;
            $_h['content-length'] = strlen($body);
            $body .= $boundary;
            empty($options['method']) and $options['method'] = 'POST';
        } else {
            $body = null;
        }

        !empty($options['method']) and $meth = strtoupper($options['method']) or $meth = 'GET';

        if ($head) {
            if (!is_array($head)) {
                $head = explode("\r\n", $head);
            }
            foreach ($head as $i => $v) {
                if (is_int($i)) {
                    $v = explode(':', $v, 2);
                    if (count($v) != 2) {
                        continue;
                    }
                    // Invalid header
                    list($i, $v) = $v;
                }
                $i      = strtolower(strtr($i, ' _', '--'));
                $_h[$i] = trim($v);
            }
        }

        if (@$options['decode'] == 'gzip') {
            // if(self::gz_supported()) {
            $_h['accept-encoding'] = 'gzip';
            // }
            // else {
            // $options['decode'] = NULL;
            // }
        }

        if (!isset($options['close']) || @$options['close']) {
            $_h['connection'] = 'close';
        } else {
            $_h['connection'] = 'keep-alive';
        }

        $prot = empty($options['protocol']) ? 'HTTP/1.1' : $options['protocol'];

        $head = array("$meth $path $prot");
        foreach ($_h as $i => $v) {
            $i = explode('-', $i);
            foreach ($i as &$j) {
                $j = ucfirst($j);
            }

            $i      = implode('-', $i);
            $head[] = $i . ': ' . $v;
        }
        $rqst = implode("\r\n", $head) . $boundary . $body;
        $head = null; // free mem

        $timeout = isset($options['timeout']) ? $options['timeout'] : @ini_get('default_socket_timeout');

        $ret->options = $options;

        // ------------------- Connection and data transfer -------------------
        $errno  = 0;
        $errstr =
            $rsps   = '';
        $h      = $_rh      = null;
        $fs     = @fsockopen($conhost, $port, $errno, $errstr, $timeout);
        if (!$fs) {
            throw new \Exception('unable to create socket "' . $conhost . ':' . $port . '" ' . $errstr, $errno);
        }
        if (!fwrite($fs, $rqst)) {
            @fclose($fs);
            throw new \Exception('unable to write to socket');
        }

        $l = $blen - 1;
        // read headers
        while ($open = !feof($fs) && ($p = @fgets($fs, 1024))) {
            if ("\r\n" == $p) {
                break;
            }

            $rsps .= $p;
        }

        if (!$rsps && !$open) {
            fclose($fs);
            throw new \Exception('unable to read from socket or empty response');
        }

        $h                          = explode("\r\n", rtrim($rsps));
        list($rprot, $rcode, $rmsg) = explode(' ', array_shift($h), 3);
        foreach ($h as $v) {
            $v = explode(':', $v, 2);
            $k = strtoupper(strtr($v[0], '- ', '__'));
            $v = isset($v[1]) ? trim($v[1]) : null;

            // Gather headers
            if (isset($_rh[$k])) {
                if (isset($v)) {
                    if (is_array($_rh[$k])) {
                        $_rh[$k][] = $v;
                    } else {
                        $_rh[$k] = array($_rh[$k], $v);
                    }
                }
            } else {
                $_rh[$k] = $v;
            }
        }
        $rsps             = null;
        $_preserve_method = true;
        switch ($rcode) {
            case 301:
            case 302:
            case 303:
                $_preserve_method = false;
            case 307:
            case 308:
                // repeat request using the same method and post data
                if (@$options['redirects'] > 0 && $loc = @$_rh['LOCATION']) {
                    if (!empty($options['host'])) {
                        $host = $options['host'];
                    }
                    is_array($loc) and $loc = end($loc);
                    $loc                    = self::abs_url($loc, compact('host', 'port', 'path') + array('scheme' => empty($options['scheme']) ? '' : $options['scheme']));
                    unset($_h['host'], $options['host'], $options['port'], $options['scheme']);
                    if (isset($options['redirect_method'])) {
                        $redirect_method = $options['redirect_method'];
                        if (is_string($redirect_method)) {
                            $options['method'] = $redirect_method = strtoupper($redirect_method);
                            $_preserve_method  = true;
                            if ('POST' != $redirect_method && 'PUT' != $redirect_method && 'DELETE' != $redirect_method) {
                                $body = null;
                            }
                        } else {
                            $_preserve_method = (bool) $redirect_method;
                        }
                    }
                    if (!$_preserve_method) {
                        $body = null;
                        unset($options['method']);
                    }
                    --$options['redirects'];
                    // ??? could save cookies for redirect
                    if (!empty($_rh['SET_COOKIE']) && !empty($options['use_cookies'])) {
                        $t = self::parse_cookie((array) $_rh['SET_COOKIE']);
                        if ($t) {
                            $now = time();
                            // @TODO: Filter out cookies by $c['domain'] and $c['path'] (compare to $loc)
                            foreach ($t as $c) {
                                if (empty($c['expires']) || $c['expires'] >= $now) {
                                    $_h['cookie'] = (empty($_h['cookie']) ? '' : $_h['cookie'] . '; ') .
                                        $c['key'] . '=' . $c['value'];
                                }
                            }
                        }
                    }
                    return self::http_wr($loc, $_h, $body, $options);
                }
                break;
        }

        // Detect body length
        if (@!$open || $rcode < 200 || 204 == $rcode || 304 == $rcode || 'HEAD' == $meth) {
            $te = 1;
        } elseif (isset($_rh['TRANSFER_ENCODING']) && strtolower($_rh['TRANSFER_ENCODING']) === 'chunked') {
            $te = 3;
        } elseif (isset($_rh['CONTENT_LENGTH'])) {
            $bl = (int) $_rh['CONTENT_LENGTH'];
            $te = 2;
        } else {
            $te = 0; // useless, just to avoid Notice: Undefined variable: te...
        }

        switch ($te) {
            case 1:
                break;
            case 2:
                while ($bl > 0 and $open &= !feof($fs) && ($p = @fread($fs, $bl))) {
                    $rsps .= $p;
                    $bl -= strlen($p);
                }
                break;
            case 3:
                while ($open &= !feof($fs) && ($p = @fgets($fs, 1024))) {
                    $_re = explode(';', rtrim($p));
                    $cs  = reset($_re);
                    $bl  = hexdec($cs);
                    if (!$bl) {
                        break;
                    }
                    // empty chunk
                    while ($bl > 0 and $open &= !feof($fs) && ($p = @fread($fs, $bl))) {
                        $rsps .= $p;
                        $bl -= strlen($p);
                    }
                    @fgets($fs, 3); // \r\n
                }
                if ($open &= !feof($fs) && ($p = @fgets($fs, 1024))) {
                    if ($p = rtrim($p)) {
                        // ??? Trailer Header
                        $v = explode(':', $p, 2);
                        $k = strtoupper(strtr($v[0], '- ', '__'));
                        $v = isset($v[1]) ? trim($v[1]) : null;

                        // Gather headers
                        if (isset($_rh[$k])) {
                            if (isset($v)) {
                                if (is_array($_rh[$k])) {
                                    $_rh[$k][] = $v;
                                } else {
                                    $_rh[$k] = array($_rh[$k], $v);
                                }
                            }
                        } else {
                            $_rh[$k] = $v;
                        }

                        @fgets($fs, 3); // \r\n
                    }
                }
                break;
            default:
                while ($open &= !feof($fs) && ($p = @fread($fs, 1024))) {
                    // ???
                    $rsps .= $p;
                }
                break;
        }

        fclose($fs);

        if (
            '' != $rsps &&
            isset($options['decode']) && 'gzip' == $options['decode'] &&
            isset($_rh['CONTENT_ENCODING']) && 'gzip' == $_rh['CONTENT_ENCODING']
        ) {
            $r = self::gzdecode($rsps);
            if (false !== $r) {
                unset($_rh['CONTENT_ENCODING']);
                $rsps = $r;
            } else {
                throw new \Exception("Can't gzdecode(response), try ['decode' => false] option");
            }
        }
        $ret->code    = $rcode;
        $ret->msg     = $rmsg;
        $ret->headers = isset($_rh) ? $_rh : null;
        $ret->body    = $rsps;
        $ret->method  = $meth;
        // $ret->host    = $host;
        $ret->port    = $port;
        $ret->path    = $path;
        $ret->request = $rqst;

        return $ret;

        // Old return:
        //     contents  headers  status-code  status-message
        // return array( $rsps,    @$_rh,   $rcode,      $rmsg,           $host, $port, $path, $rqst  );
    }

    // ------------------------------------------------------------------------

}

// ------------------------------------------------------------------------
// PSR-0 alias
class_exists('hQuery', false) or class_alias('duzun\\hQuery', 'hQuery', false);
