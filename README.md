hQuery.php   [![Build Status](https://travis-ci.org/duzun/hQuery.php.svg?branch=master)](https://travis-ci.org/duzun/hQuery.php)
==========

An extremely fast and efficient web scraper that parses megabytes of HTML in a blink of an eye.


# Features

  - Very fast parsing and lookup
  - Parses broken HTML
  - jQuery-like style of DOM traversal
  - Low memory usage
  - Can handle big HTML documents (I have tested up to 20Mb, but the limit is the amount of RAM you have)
  - Doesn't require cURL to be installed
  - Automatically handles redirects (301, 302, 303)
  - Caches response for multiple processing tasks
  - PHP 5+

# Usage

[API Documentation](https://duzun.github.io/hQuery.php/docs/class-hQuery.html)

### Basic setup:
```php
include '/path/to/libs/hquery.php';

// Set the cache path - must be a writable folder
hQuery::$cache_path = "/path/to/cache";
```

### Open a remote HTML document
###### [hQuery::fromUrl](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_fromURL)( string `$url`, array `$headers` = NULL, array|string `$body` = NULL, array `$options` = NULL )
```php
$doc = hQuery::fromUrl('http://example.com/someDoc.html', ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);

var_dump($doc->headers); // See response headers
var_dump(hQuery::$last_http_result); // See response details of last request
```
For building advanced requests (POST, parameters etc) see [hQuery::http_wr()](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_http_wr)

### Open a local HTML document
###### [hQuery::fromFile](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_fromFile)( string `$filename`, boolean `$use_include_path` = false, resource `$context` = NULL )
```php
$doc = hQuery::fromFile('/path/to/filesystem/doc.html');
```
### Load HTML from a string
###### [hQuery::fromHTML](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_fromHTML)( string `$html`, string `$url` = NULL )
```php
$doc = hQuery::fromHTML('<html><head><title>Sample HTML Doc</title><body>Contents...</body></html>');

// Set base_url, in case the document is loaded from local source.
// Note: The base_url is used to retrive absolute URLs from relative ones
$doc->base_url = 'http://desired-host.net/path';
```

### Processing the results
###### [hQuery::find](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_find)( string `$sel`, array|string `$attr` = NULL, hQuery_Node `$ctx` = NULL )
```php
// Find all banners (images inside anchors)
$banners = $doc->find('a > img:parent');

// Extract links and images
$links  = array();
$images = array();
$titles = array();
foreach($banners as $pos => $a) {
    $links[$pos] = $a->attr('href');
    $titles[$pos] = trim($a->text()); // strip all HTML tags and leave just text
    $images[$pos] = $a->find('img')->attr('src');
}

// Read charset of the original document (internally it is converted to UTF-8)
$charset = $doc->charset;

// Get the size of the document ( strlen($html) )
$size = $doc->size;
```

# Live Demo
  On [DUzun.Me](https://duzun.me/playground/hquery#sel=%20a%20%3E%20img%3Aparent&url=https%3A%2F%2Fgithub.com%2Fduzun)

#TODO

  - Unit tests everything
  - Document everything
  - Cookie support
  - Add more selectors
  - Improve selectors to be able to select by attributes

