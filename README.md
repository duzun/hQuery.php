hQuery.php
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
  
    include '/path/to/libs/hquery.php';
  
    // Set the cache path - must be a writable folder
    hQuery::$cache_path = "/path/to/cache";

    // Open a remote HTML document
    $doc = hQuery::fromUrl('http://example.com/someDoc.html');
  
    // Open a local HTML document
    $doc = hQuery::fromFile('/path/to/filesystem/doc.html');
  
    // Load HTML from a string
    $doc = hQuery::fromHTML('<html><head><title>Sample HTML Doc</title><body>Contents...</body></html>');
  
    // Set base_url, in case the document is loaded from local source.
    // Note: The base_url is used to retrive absolute URLs from relative ones
    $doc->base_url = 'http://desired-host.net/path';
  
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
  
    // Get the size of the document ( strlen($heml) )
    $size = $doc->size;
  
  
#TODO

  - Document everything
  - Add more selectors
  - Improve selectors to be able to select by attributes

