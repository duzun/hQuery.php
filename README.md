hQuery.php  [![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.me/duzuns)
==========

An extremely fast and efficient web scraper that can parse megabytes of invalid HTML in a blink of an eye.

You can use the familiar jQuery/CSS selector syntax to easily find the data you need.

In my unit tests, I demand it be at least 10 times faster than Symfony's DOMCrawler on a 3Mb HTML document.
In reality, according to my humble tests, it is two-three orders of magnitude faster than DOMCrawler in some cases, especially when
selecting thousands of elements, and on average uses x2 less RAM.

See [tests/README.md](https://github.com/duzun/hQuery.php/blob/master/tests/README.md).

[API Documentation](https://duzun.github.io/hQuery.php/docs/class-hQuery.html)

## 💡 Features

- Very fast parsing and lookup
- Parses broken HTML
- jQuery-like style of DOM traversal
- Low memory usage
- Can handle big HTML documents (I have tested up to 20Mb, but the limit is the amount of RAM you have)
- Doesn't require cURL to be installed and automatically handles redirects (see [hQuery::fromUrl()](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_fromURL))
- Caches response for multiple processing tasks
- [PSR-7](https://www.php-fig.org/psr/psr-7/) friendly (see hQuery::fromHTML($message))
- PHP 5.3+
- No dependencies

## 🛠 Install

Just add this folder to your project and `include_once 'hquery.php';` and you are ready to `hQuery`.

Alternatively `composer require duzun/hquery`

or using `npm install hquery.php`, `require_once 'node_modules/hquery.php/hquery.php';`.

## ⚙ Usage

### Basic setup:

```php
// Optionally use namespaces
use duzun\hQuery;

// Either use composer, or include this file:
include_once '/path/to/libs/hquery.php';

// Set the cache path - must be a writable folder
// If not set, hQuery::fromURL() would make a new request on each call
hQuery::$cache_path = "/path/to/cache";

// Time to keep request data in cache, seconds
// A value of 0 disables cache
hQuery::$cache_expires = 3600; // default one hour
```

I would recommend using [php-http/cache-plugin](http://docs.php-http.org/en/latest/plugins/cache.html)
with a [PSR-7 client](http://docs.php-http.org/en/latest/clients.html) for better flexibility.

### Load HTML from a file
###### [hQuery::fromFile](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_fromFile)( string `$filename`, boolean `$use_include_path` = false, resource `$context` = NULL )

```php
// Local
$doc = hQuery::fromFile('/path/to/filesystem/doc.html');

// Remote
$doc = hQuery::fromFile('https://example.com/', false, $context);
```

Where `$context` is created with [stream_context_create()](https://secure.php.net/manual/en/function.stream-context-create.php).

For an example of using `$context` to make a HTTP request with proxy see [#26](https://github.com/duzun/hQuery.php/issues/26#issuecomment-351032382).

### Load HTML from a string
###### [hQuery::fromHTML](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_fromHTML)( string `$html`, string `$url` = NULL )

```php
$doc = hQuery::fromHTML('<html><head><title>Sample HTML Doc</title><body>Contents...</body></html>');

// Set base_url, in case the document is loaded from local source.
// Note: The base_url property is used to retrieve absolute URLs from relative ones.
$doc->base_url = 'http://desired-host.net/path';
```

### Load a remote HTML document
###### [hQuery::fromUrl](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_fromURL)( string `$url`, array `$headers` = NULL, array|string `$body` = NULL, array `$options` = NULL )

```php
use duzun\hQuery;

// GET the document
$doc = hQuery::fromUrl('http://example.com/someDoc.html', ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);

var_dump($doc->headers); // See response headers
var_dump(hQuery::$last_http_result); // See response details of last request

// with POST
$doc = hQuery::fromUrl(
    'http://example.com/someDoc.html', // url
    ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'], // headers
    ['username' => 'Me', 'fullname' => 'Just Me'], // request body - could be a string as well
    ['method' => 'POST', 'timeout' => 7, 'redirect' => 7, 'decode' => 'gzip'] // options
);

```

For building advanced requests (POST, parameters etc) see [hQuery::http_wr()](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_http_wr),
though I recommend using a specialized ([PSR-7](https://www.php-fig.org/psr/psr-7/)?) library for making requests
and `hQuery::fromHTML($html, $url=NULL)` for processing results.
See [Guzzle](http://docs.guzzlephp.org/en/stable/) for eg.

#### [PSR-7](https://www.php-fig.org/psr/psr-7/) example:

```sh
composer require php-http/message php-http/discovery php-http/curl-client
```

If you don't have [cURL PHP extension](https://secure.php.net/curl),
just replace `php-http/curl-client` with `php-http/socket-client` in the above command.

```php
use duzun\hQuery;

use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;

$client = HttpClientDiscovery::find();
$messageFactory = MessageFactoryDiscovery::find();

$request = $messageFactory->createRequest(
  'GET',
  'http://example.com/someDoc.html',
  ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']
);

$response = $client->sendRequest($request);

$doc = hQuery::fromHTML($response, $request->getUri());

```

Another option is to use [stream_context_create()](https://secure.php.net/manual/en/function.stream-context-create.php)
to create a `$context`, then call `hQuery::fromFile($url, false, $context)`.

### Processing the results
###### [hQuery::find](https://duzun.github.io/hQuery.php/docs/class-hQuery.html#_find)( string `$sel`, array|string `$attr` = NULL, hQuery\Node `$ctx` = NULL )

```php
// Find all banners (images inside anchors)
$banners = $doc->find('a[href] > img[src]:parent');

// Extract links and images
$links  = array();
$images = array();
$titles = array();

// If the result of find() is not empty
// $banners is a collection of elements (hQuery\Element)
if ( $banners ) {

    // Iterate over the result
    foreach($banners as $pos => $a) {
        $links[$pos] = $a->attr('href'); // get absolute URL from href property
        $titles[$pos] = trim($a->text()); // strip all HTML tags and leave just text

        // Filter the result
        if ( !$a->hasClass('logo') ) {
            // $a->style property is the parsed $a->attr('style')
            if ( strtolower($a->style['position']) == 'fixed' ) continue;

            $img = $a->find('img')[0]; // ArrayAccess
            if ( $img ) $images[$pos] = $img->src; // short for $img->attr('src')
        }
    }

    // If at least one element has the class .home
    if ( $banners->hasClass('home') ) {
        echo 'There is .home button!', PHP_EOL;

        // ArrayAccess for elements and properties.
        if ( $banners[0]['href'] == '/' ) {
            echo 'And it is the first one!';
        }
    }
}

// Read charset of the original document (internally it is converted to UTF-8)
$charset = $doc->charset;

// Get the size of the document ( strlen($html) )
$size = $doc->size;
```

Note: In case the charset meta attribute has a wrong value or the internal conversion fails for any other reason, `hQuery` would ignore the error and continue processing with the original HTML, but would register an error message on `$doc->html_errors['convert_encoding']`.

## 🖧 Live Demo

On [DUzun.Me](https://duzun.me/playground/hquery#sel=%20a%20%3E%20img%3Aparent&url=https%3A%2F%2Fgithub.com%2Fduzun)

A lot of people ask for sources of my **Live Demo** page. Here we go:

[view-source:https://duzun.me/playground/hquery](https://github.com/duzun/hQuery.php/blob/master/examples/duzun.me_playground_hquery.php)

### 🏃 Run the playground

You can easily run any of the `examples/` on your local machine.
All you need is PHP installed in your system.
After you clone the repo with `git clone https://github.com/duzun/hQuery.php.git`,
you have several options to start a web-server.

###### Option 1:

```sh
cd hQuery.php/examples
php -S localhost:8000

# open browser http://localhost:8000/
```

###### Option 2 (browser-sync):

This option starts a live-reload server and is good for playing with the code.

```sh
npm install
gulp

# open browser http://localhost:8080/
```

###### Option 3 (VSCode):

If you are using VSCode, simply open the project and run debugger (`F5`).

## 🔧 TODO

- Unit tests everything
- Document everything
- ~~Cookie support~~ (implemented in mem for redirects)
- ~~Improve selectors to be able to select by attributes~~
- Add more selectors
- Use [HTTPlug](http://httplug.io/) internally

## 💖 Support my projects

I love Open Source. Whenever possible I share cool things with the world (check out [NPM](https://duzun.me/npm) and [GitHub](https://github.com/duzun/)).

If you like what I'm doing and this project helps you reduce time to develop, please consider to:

- ★ Star and Share the projects you like (and use)
- ☕ Give me a cup of coffee - [PayPal.me/duzuns](https://www.paypal.me/duzuns) (contact at duzun.me)
- ₿ Send me some **Bitcoin** at this addres: `bitcoin:3MVaNQocuyRUzUNsTbmzQC8rPUQMC9qafa` (or using the QR below)
![bitcoin:3MVaNQocuyRUzUNsTbmzQC8rPUQMC9qafa](https://cdn.duzun.me/files/qr_bitcoin-3MVaNQocuyRUzUNsTbmzQC8rPUQMC9qafa.png)
