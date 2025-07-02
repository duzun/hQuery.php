<?php
namespace Tests;

use duzun\hQuery;
use duzun\hQuery\Element;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Mock\Client;
use PHPUnit\Framework\Attributes\Depends;
use Tests\Lib\PHPUnit_BaseClass;

// -----------------------------------------------------
/**
 *  @TODO: Test all methods
 *  @author DUzun.Me
 */
// -----------------------------------------------------
require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

// -----------------------------------------------------
// class_alias(hQuery::class, 'Tests\Lib\hQueryTestSurrogate');

// Surrogate class for testing, to access protected attributes of hQuery
class hQueryTestSurrogate extends hQuery
{
    /**
     * @var mixed
     */
    public $class_idx;
}

// -----------------------------------------------------

class hQueryCore extends PHPUnit_BaseClass
{
    // -----------------------------------------------------
    /**
     * @var hQueryTestSurrogate
     */
    public static $inst;

    /**
     * @var mixed
     */
    public static $messageFactory;

    /**
     * @var boolean
     */
    public static $log = true;

    /**
     * @var string
     */
    public static $className = 'duzun\hQuery';

    /**
     * @var string
     */
    public static $baseUrl = 'https://DUzun.Me/';

    /**
     * @var string
     */
    public static $bodyHTML = <<<EOS
<!doctype html>
<html>
<head>
    <meta charset="ISO-8859-2">
    <!-- <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-2" /> -->
    <meta content="/logo.png" property="og:image"/>
    <title>Sample HTML Doc</title>
    <link rel="shortcut icon" href='/favicon.ico' class=pjax />
</head>
<body class="test-class">
    <div id="test-div" class="test-class test-div span-div">
        text: This is some text
        <a href="/path" class="path span span-a">
            link: This is a link
        </a>
         in : between tags
        span: <span id="aSpan" class="span span-span">Span text</span>
        notSpan: <div id="aDiv" class="span span-div">notSpan text</div>
    </div>
    <a id="outerLink"
        href="//not-my-site.com/next.html"
        style="Color:blue;padding: 1px 2pt 3em 0; background-image:url(/path/to/img.jpg?url=param&and=another&one);"
    >Not My Site</a>
    <img id="outerImg" src="//cdn.duzun.me/images/logo.png" />

    <dl id="dict1">
      <dt>Coffee</dt>
      <dd>Black hot drink</dd>
      <dt>Milk</dt>
      <dd>White cold drink</dd>
    </dl>

    <table id="dict2">
        <tr>
            <th class=" "  >Coffee</th>
            <td>Black hot drink</td>
        </tr>
        <tr>
            <th>Milk</th>
            <td>White cold drink</td>
        </tr>
    </table>


    <div id="dict3">
      <span><b>Coffee:</b> Black hot drink</span>
      <span><b>Milk:</b> White cold drink</span>
    </div>

    Contents...
</body>
</html>
EOS;

    public static $emptyBodyHTML = <<<EOS
    <?xml version="1.0" encoding="windows-1251"?>
    <html>
    <head>
    <meta name="robots" content="noindex,nofollow">
    <script src="xxx"></script>
    <body>
    </body></html>
EOS;

    public static $badHTML1 = '<iframe><meta http-equiv="refresh" content="1;/>';

    public static $badHTML2 = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=uft-8" /></head><body><a>A</a></body></html><';

    public static $baseTag1 = '<!doctype html>
<html>
<head>
    <meta content="/logo.png" property="og:image"/>
    <base href="/base/path.html?how=rewrite#hash" />
    <link rel="shortcut icon" href="/favicon.ico" class=pjax />
</head>
<body class="test-class">
    <a href="rel-path/index.html" id="rel_path">relative path</a>
    <a href="/abs-path/index.html" id="rel_origin">relative origin</a>
    <a href="//not-my-site.com/next.html" id="rel_schema">relative schema</a>
    <img id="rel_img" src="/images/logo.png" />
</body>
</html>';

    // Before any test
    public static function mySetUpBeforeClass()
    {
        hQuery::$_mockup_class = hQueryTestSurrogate::class;

        self::$inst = hQueryTestSurrogate::fromHTML(self::$bodyHTML, self::$baseUrl . 'index.html');

        // self::log(get_class(self::$inst));
    }

    // After all tests
    public static function myTearDownAfterClass()
    {
        self::$inst = null;
    }

    // -----------------------------------------------------
    // -----------------------------------------------------

    public function testClass()
    {
        assertMehodExists('fromHTML', self::$className);
        assertMehodExists('fromFile', self::$className);
        assertMehodExists('fromURL', self::$className);

        // $f = glob(TESTS_DIR . 'data/*');
        // foreach($f as $k => $v) if(is_file($v) && substr($v, -3) != '.gz') {
        // $g = gzencode(file_get_contents($v), 9);
        // file_put_contents($v.'.gz', $g);
        // }
    }

    // -----------------------------------------------------
    public function test_fromHTML()
    {
        $url = strtolower(self::$baseUrl . 'index.html');

        // self::$inst is initialized with ::fromHTML() method
        $doc = self::$inst;
        assertEquals(self::$bodyHTML, $doc->html());
        assertEquals(self::$baseUrl . 'index.html', $doc->location());

        // Optional stuff
        if (class_exists('Http\Discovery\MessageFactoryDiscovery')) {
            empty(self::$messageFactory) and self::$messageFactory = MessageFactoryDiscovery::find();

            // $response = $this->createMock('Psr\Http\Message\ResponseInterface');
            $response = self::$messageFactory->createResponse(
                '200',
                'ok',
                array('host' => parse_url(self::$baseUrl, PHP_URL_HOST), 'origin' => self::$baseUrl),
                self::$bodyHTML
            );

            // Document from a Psr\Http\Message\ResponseInterface object
            $doc = hQueryTestSurrogate::fromHTML($response, $url);
            assertEquals(self::$bodyHTML, $doc->html());
            assertEquals($url, $doc->location());

            $request = self::$messageFactory->createRequest(
                'GET',
                $url,
                array(),
                self::$bodyHTML
            );

            // Document from a Psr\Http\Message\RequestInterface object
            $doc = hQueryTestSurrogate::fromHTML($request);
            assertEquals(self::$bodyHTML, $doc->html());
            assertEquals($url, $doc->location());
        } else {
            self::log('Http\Discovery\MessageFactoryDiscovery not found!');
        }

        if (class_exists('Http\Mock\Client')) {
            // Document from hQuery::sendRequest($request, $client)
            $client = new Client();
            $client->addResponse($response);

            $doc = hQueryTestSurrogate::sendRequest($request, $client);
            assertEquals(self::$bodyHTML, $doc->html());
            assertEquals($url, $doc->location());
        } else {
            self::log('Http\Mock\Client not found!');
        }

        // And to make sure the $doc is realy ok
        assertEquals('Sample HTML Doc', $doc->find('head title')->text);


        // The empty HTML doc:
        $emptyDoc = $doc::fromHTML('', $url, ['content-type' => 'text/xml; charset=ascii']);
        assertInstanceOf(get_class($doc), $emptyDoc);
        assertEquals('', $emptyDoc->html);
        assertEquals('ASCII', $emptyDoc->charset);

        // Bad HTML
        $badDoc = hQueryTestSurrogate::fromHTML(self::$badHTML1);
        assertInstanceOf(get_class($doc), $badDoc);
        assertEquals(2, count($badDoc));

        // Bad HTML charset
        $badDoc = hQueryTestSurrogate::fromHTML(self::$badHTML2, $url, ['content-type' => 'text/html; charset=ascii']);
        assertInstanceOf(get_class($doc), $badDoc);
        assertEquals('UFT-8', $badDoc->charset);
        assertNotEmpty($badDoc->html_errors['convert_encoding']);
        assertEquals(5, count($badDoc));
        $a = $badDoc->find('a');
        assertEquals('A', $a->text);
    }

    // -----------------------------------------------------
    /**
     * @return mixed
     */
    public function test_find()
    {
        $doc = self::$inst;
        $body = $doc->find('body');

        // @TODO:
        // :parent>div:last
        // >div:last

        // 1)
        $a = $doc->find('.test-class #test-div.test-div > a[href]');

        assertNotEmpty($a);
        assertEquals(1, count($a));
        assertTrue($a instanceof Element);
        assertEquals('a', $a->nodeName);
        assertEquals('link: This is a link', trim($a->text));
        assertEquals('https://DUzun.Me/path', $a->href);
        assertEquals('/path', $a->attr('href'));
        assertEquals('div', $a->parent->nodeName);
        assertEquals('test-div', $a->parent->attr('id'));

        $a = $doc->find('.test-class [id=test-div].test-div.span-div > a[href].path.span-a');
        assertEquals(1, count($a));
        assertEquals('a', $a->nodeName);

        $a = $doc->find('.test-class a[href][class="path span span-a"]');
        assertEquals(1, count($a));
        assertEquals('a', $a->nodeName);

        $a = $doc->find('.test-class [class="path span span-a"]');
        assertEquals(1, count($a));
        assertEquals('a', $a->nodeName);

        $a = $doc->find($sel = '[class="path span span-a"]');
        assertEquals(1, count($a));
        assertEquals('a', $a->nodeName);

        $a = $doc->find($sel = 'th[class=" "]');
        assertEquals(1, count($a));
        assertEquals('th', $a->nodeName);
        assertEquals('Coffee', trim($a->text));

        $b    = $body->find($sel);
        assertNotNull($b, $sel);
        assertEquals(count($a), count($b), $sel);

        $a = $doc->find('#outerImg');
        assertNotEmpty($a);
        assertEquals('img', $a->nodeName);

        $a = $doc->find('dl>dt+dd');
        assertNotEmpty($a);
        assertEquals(2, count($a));

        $a = $doc->find('div + a');
        assertNotEmpty($a);
        assertEquals(1, count($a));

        $a = $doc->find('div + img');
        assertEmpty($a);

        $a = $doc->find('div ~ img');
        assertNotEmpty($a);
        assertEquals(1, count($a));

        $a = $doc->find('.span');
        assertEquals(3, count($a));

        $a = $doc->find('.span.span-div');
        assertEquals(1, count($a));

        $a = $doc->find('.span-a.span-div');
        assertEmpty($a);

        $a = $doc->find('a ~ .span');
        assertNotEmpty($a);
        assertEquals(2, count($a));

        // 2)
        $ff = hQueryTestSurrogate::fromFile(self::file_exists('data/attr.html'));
        $aa = $ff->find('a.aa');
        assertEquals(3, count($aa));

        // self::log($ff->_info());

        // 3)
        $input = $ff->find('input');
        assertEquals(3, count($input));

        $input = $ff->find('input[name=title]');
        assertEquals(1, count($input));
        assertEquals('the title', $input->value);

        $input = $ff->find('input[type=text]');
        assertEquals(2, count($input));

        $input = $ff->find('input[type=text][name=text]');
        assertEquals(1, count($input));
        assertEquals('the text', $input->attr('value'));

        // 4)
        $a = $doc->find('[href]');
        assertEquals(3, count($a));

        $a = self::$inst->find('[href][class]');
        assertEquals(2, count($a));

        $a = self::$inst->find('[href][class=pjax]');
        $b = self::$inst->find('[href].pjax');
        assertEquals(1, count($a));
        assertEquals(1, count($b));
        assertEquals($a->key(), $b->key());

        // 5)
        $edoc = hQueryTestSurrogate::fromHTML(self::$emptyBodyHTML, self::$baseUrl . 'index.html');
        $a = $edoc->find('a');
        assertEmpty($a);

        // there is no </head> closing tag, thus meta is not inside of <head>
        $a = $edoc->find('head meta');
        assertEmpty($a);

        $b = $edoc->find('body');
        assertEquals(1, count($b));

        // 6)
        $bdoc = hQueryTestSurrogate::fromHTML(self::$badHTML1, self::$baseUrl . 'index.html');
        $a = $bdoc->find('iframe');
        assertEquals(1, count($a));

        $b = $bdoc->find('meta');
        assertEquals(1, count($b));

        return $ff;
    }

    // -----------------------------------------------------
    public function test_hasClass()
    {
        $doc = self::$inst;

        $a     = $doc->find('a:first');
        $div   = $doc->find('div.test-div');
        $body  = $doc->find('body');
        $head  = $doc->find('head');
        $all   = $doc->find('.test-class');
        $empty = $head->slice(0, 0);

        assertNotEmpty($div->hasClass('test-class'), 'div.test-div should have .test-class class');
        assertNotEmpty($div->hasClass(array('test-class', 'test-div')), 'div.test-div should have .test-div and .test-class class');
        assertEmpty($div->hasClass(array('test-class', 'test-div', 'span')), 'div.test-div shouldn\'t have .no-class class');
        assertNotEmpty($all->hasClass('test-class test-div'), 'At least one div should have .test-div and .test-class classes');

        assertEmpty($a->hasClass('test-class'), 'a should have no class');
        assertEmpty($body->hasClass('test-div'), 'body should not have "test-dev" class');
        assertEmpty($body->hasClass('test-class test-div'), 'body doesn\'t have both classes .test-div and .test-class');

        // Some edge cases
        assertEmpty($a->hasClass('non-existent-class'), 'non existent classes should not throw');
        assertEmpty($head->hasClass('non-existent-class'), 'non existent classes should not throw even on elements with non attributes at all');
        assertEmpty($div->hasClass(array('non-existent-class', 'span')), 'non existent classes should not throw even when in combination with existing classes');
        assertEmpty($a->hasClass(''), 'empty class doesn\'t exist');
        assertEmpty($a->hasClass(array()), 'empty class doesn\'t exist');
        assertEmpty($empty->hasClass('test-class'), 'empty collection should not have any class');
        assertEmpty($empty->hasClass('non-existent-class'), 'non existent classes should not throw even on empty collections');

        // More speciffic
        assertEquals(0, $a->hasClass('test-class'), 'a should have no class');
        assertEquals(false, $body->hasClass('test-div'), 'body should not have .test-div class');
        assertEquals(true, $div->hasClass('test-div'), 'a should have .test-div class');
    }

    // -----------------------------------------------------
    /**
     * @depends test_find
     */
     #[Depends('test_find')]
    public function test_hQuery_Element_ArrayAccess($doc)
    {
        $e = $doc->find('input');

        // Short forms of $e->get(0)->attr('name')
        assertEquals('title', $e->get(0)->attr('name'));
        assertEquals('title', $e[0]->name);
        assertEquals('title', $e[0]['name']);
        assertEquals('title', $e['name']);
        assertEquals('title', $e->name);

        assertEquals('text', $e[1]['name']);
        assertEquals('random', $e[2]['name']);

        return $doc;
    }

    // -----------------------------------------------------
    /**
     * @depends test_hQuery_Element_ArrayAccess
     */
     #[Depends('test_hQuery_Element_ArrayAccess')]
    public function test_attr_and_prop($doc)
    {
        // Note: there is no baseURI for $doc at this point.
        $e = $doc->find('#img1');
        $a = $doc->find('a.aa:last');

        // It's magic!
        assertEquals($e->src, $e->attr('src'));
        assertEquals($e->src1, $e->attr('src1'));
        assertEquals($e->src2, $e->attr('src2'));
        assertEquals($a->href, $a->attr('href'));

        // Standard way of accessing attributes:
        assertEquals('/path/to/img.png', $e->attr('src'));
        assertEquals('other/img/here.jpg', $e->attr('src2'));
        assertEquals('//example.com/full/path.gif', $e->attr('src3'));
        assertEquals('#test', $a->attr('href'));

        // $doc was loaded from the file "data/attr.html" and has no baseURI associated.
        // Set the baseURI from document location, so that .href and .src props
        // would be resolved.
        $doc->location(self::$baseUrl);

        // Properties are evaluated semantically:
        assertEquals(self::$baseUrl . 'path/to/img.png', $e->src);
        assertEquals('other/img/here.jpg', $e->src2); // .src2 ain't special

        assertEquals(self::$baseUrl . '#test', $a->href);
        assertEquals('#test', $a->attr('href'));


        // Relative vs Absolute URL paths

        // a[href] relative URL
        $a = self::$inst->find('a:first');
        assertEquals(self::$baseUrl . 'path', $a->href);
        assertEquals('/path', $a->attr('href'));

        // a[href] absolute URL
        $a = self::$inst->find('a#outerLink');
        assertEquals('https://not-my-site.com/next.html', $a->href);
        assertEquals('//not-my-site.com/next.html', $a->attr('href'));

        // $a->style is the parsed $a->attr('style'):
        assertNotEmpty($a->style);
        assertNotEquals($a->attr('style'), $a->style);
        assertTrue(is_array($a->style));
        assertTrue(is_string($a->attr('style')));
        assertEquals(array('color', 'padding', 'background-image'), array_keys($a->style));
        assertEquals('blue', $a->style['color']);


        // img[src] absolute URL
        $a = self::$inst->find('img#outerImg');
        assertEquals('https://cdn.duzun.me/images/logo.png', $a->src);
        assertEquals('//cdn.duzun.me/images/logo.png', $a->attr('src'));

        // link[href] relative URL
        $a = self::$inst->find('link', array('rel' => 'shortcut icon'));
        assertEquals(self::$baseUrl . 'favicon.ico', $a->href);
        assertEquals('/favicon.ico', $a->attr('href'));

        // meta[content] - not a URL
        $m = self::$inst->find('meta', array('property' => 'og:image'));
        $c = $m->attr('content');

        assertEquals('/logo.png', $c);

        $c = self::$inst->url2abs($c);
        assertEquals(self::$baseUrl . 'logo.png', $c);

        return $doc;
    }

    // -----------------------------------------------------
    /**
     * @depends test_attr_and_prop
     */
     #[Depends('test_attr_and_prop')]
    public function test_prop_charset($doc)
    {
        assertEquals('utf-8', strtolower($doc->charset));
        assertEquals('iso-8859-2', strtolower(self::$inst->charset));

        return $doc;
    }

    // -----------------------------------------------------
    public function test_prop_size()
    {
        $size = self::$inst->size;
        assertGreaterThan(200, $size);
    }

    // -----------------------------------------------------
    public function test_prop_baseURL()
    {
        $baseURL = self::$inst->baseURL;
        assertEquals(self::$baseUrl, $baseURL);

        $doc = hQueryTestSurrogate::fromHTML(self::$baseTag1, self::$baseUrl . 'index.html');
        $baseURL = $doc->baseURL;
        assertEquals(self::$baseUrl . 'base/', $baseURL);

        $a = $doc->find('a#rel_path');
        assertEquals('rel-path/index.html', $a->attr('href'));
        assertEquals(self::$baseUrl . 'base/rel-path/index.html', $a->href);

        $a = $doc->find('a#rel_origin');
        assertEquals('/abs-path/index.html', $a->attr('href'));
        assertEquals(self::$baseUrl . 'abs-path/index.html', $a->href);

        $a = $doc->find('a#rel_schema');
        assertEquals('//not-my-site.com/next.html', $a->attr('href'));
        assertEquals('https://not-my-site.com/next.html', $a->href);

        $img = $doc->find('img#rel_img');
        assertEquals('/images/logo.png', $img->attr('src'));
        assertEquals(self::$baseUrl . 'images/logo.png', $img->src);

        return $doc;
    }

    // -----------------------------------------------------
    /**
     * Either the <base href=...> or the location()
     *
     * @depends test_prop_baseURL
     */
     #[Depends('test_prop_baseURL')]
    public function test_prop_baseURI($doc)
    {
        $baseURI = self::$inst->baseURI;
        assertEquals(self::$baseUrl . 'index.html', $baseURI);

        $baseURI = $doc->baseURI;
        assertEquals(self::$baseUrl . 'base/path.html?how=rewrite#hash', $baseURI);

        return $doc;
    }

    // -----------------------------------------------------
    /**
     * URI at which the doc was accessed/loaded.
     *
     * @depends test_prop_baseURI
     */
     #[Depends('test_prop_baseURI')]
    public function test_prop_href($doc)
    {
        $href = self::$inst->href;
        $location = self::$inst->location();
        assertEquals($location, $href);
        assertEquals(self::$baseUrl . 'index.html', $href);

        $href = $doc->href;
        assertEquals(self::$baseUrl . 'index.html', $href);
    }

    // -----------------------------------------------------
    public function test_text()
    {
        $div  = self::$inst->find('#test-div');
        $text = $div->text();

        assertEquals("text: This is some text\n        \n            link: This is a link\n        \n         in : between tags\n        span: Span text\n        notSpan: notSpan text", trim($text));
        assertEquals('text: This is some text link: This is a link in : between tags span: Span text notSpan: notSpan text', preg_replace('/\\s+/', ' ', trim($text)));
    }

    public function test_outterHtml() {
        $inst = self::$inst;

        // tag close style is preserved: ">" | "/>" | " />"
        $meta = $inst->find('meta[charset]');
        assertEquals('<meta charset="ISO-8859-2">', $meta->outerHtml());

        $meta = $inst->find('meta[property=og:image]');
        assertEquals('<meta content="/logo.png" property="og:image"/>', $meta->outerHtml());

        // Attributes of the top level tag are normalized (sorted by name and properly quoted)
        // In doc this link is:
        //     <link rel="shortcut icon" href='/favicon.ico' class=pjax />
        $link = $inst->find('link[rel="shortcut icon"]');
        assertEquals('<link class="pjax" href="/favicon.ico" rel="shortcut icon" />', $link->outerHtml());

        $link = $inst->find('th[class=" "]');
        assertEquals('<th class=" "  >Coffee</th>', $link->outerHtml());
    }

    public function test_text2dl()
    {
        $div = self::$inst->find('#test-div');

        // Fetch a definition list out of textContents
        $dl = $div->text2dl();
        assertEquals(array(
            'text'    => 'This is some text',
            'link'    => 'This is a link',
            'in'      => 'between tags',
            'span'    => 'Span text',
            'notSpan' => 'notSpan text',
        ), $dl);

        // Fetch one value out of definition list as text
        assertEquals('This is a link', $div->text2dl(':', 'link'));

        // Fetch one value out of definition list as text by filter function
        if (class_exists('Closure')) {
            $v = $div->text2dl(':', function ($key, $val) {
                return stripos($key, 'SPAN') !== false;
            });
            assertEquals('Span text', $v);
        }
    }

    public function test_dl()
    {
        $dl = self::$inst->find('#dict1');

        // Fetch a definition list
        $dict = $dl->dl('dt', 'dd');
        assertEquals(array(
            'Coffee' => 'Black hot drink',
            'Milk'   => 'White cold drink',
        ), $dict);

        // Fetch one value out of definition list
        assertEquals('White cold drink', $dl->dl('dt', 'dd', null, 'Milk'));

        $dl = self::$inst->find('#dict2');

        // Fetch a definition list
        $dict = $dl->dl('th', 'td', 'tr');
        assertEquals(array(
            'Coffee' => 'Black hot drink',
            'Milk'   => 'White cold drink',
        ), $dict);

        // Fetch one value out of definition list
        assertEquals('White cold drink', $dl->dl('th', 'td', 'tr', 'Milk'));

        // @TODO
        // $dl = self::$inst->find('#dict3');

        // // Fetch a definition list
        // $dict = $dl->dl('b', NULL, 'span');
        //assertEquals(array(
        //   'Coffee' => 'Black hot drink',
        //   'Milk'   => 'White cold drink',
        // ), $dict);

        // // Fetch one value out of definition list
        //assertEquals('White cold drink', $dl->dl('th', 'td', 'tr', 'Milk'));

    }

    // -----------------------------------------------------
    //     public function test_extract_text_contents() {
    //         $div = self::$inst->find('#test-div');
    //         $div->exclude('*');
    //         $html = $div->html();
    //         $text = $div->text();
    //         $pos = $div->pos();
    //         $_pos = strpos(self::$inst->html(), $div->html());
    //         self::log(compact(/*'pos', '_pos', */'html', 'text'), self::$inst->html());
        //        assertEquals('This is some text'.
    //                     '<a href="/path">'.
    //                         'This is a link'.
    //                     '</a>'.
    //                     ' between tags'.
    //                     '<span id="aSpan">Span text</span>', $html);
    //     }

    // -----------------------------------------------------
    // public function test_http_wr() {
    // @TODO
    // $doc = hQueryTestSurrogate::fromURL('http://www.nameit.com', NULL, NULL, ['redirects' => 10, 'use_cookies' => true]);
    // self::log(gettype($doc));
    // self::log(hQueryTestSurrogate::$last_http_result);
    // }

    // -----------------------------------------------------
    // -----------------------------------------------------
    public function test_detect_charset() {
        // From HTML/XML
        assertEquals(false, hQueryTestSurrogate::detect_charset(' '));
        assertEquals('ISO-8859-2', hQueryTestSurrogate::detect_charset(self::$bodyHTML));
        assertEquals('WINDOWS-1251', hQueryTestSurrogate::detect_charset(self::$emptyBodyHTML, ['content-type' => 'text/html; charset=UTF-8']));
        assertEquals(false, hQueryTestSurrogate::detect_charset(self::$badHTML1));
        assertEquals('UFT-8', hQueryTestSurrogate::detect_charset(self::$badHTML2));

        // From HTTP headers
        assertEquals('UTF-8', hQueryTestSurrogate::detect_charset('', 'Content-Type: text/html; charset=UTF-8'));
        assertEquals('UTF-8', hQueryTestSurrogate::detect_charset('', ['Content-Type' => 'text/html; charset = utf-8 ']));
        assertEquals('UTF-8', hQueryTestSurrogate::detect_charset('', ['content-type' => 'text/html; charset = "utf-8"']));
        assertEquals('UTF-8', hQueryTestSurrogate::detect_charset('', ['CONTENT_TYPE' => "text/html;charset='Utf-8'"]));
    }
    // -----------------------------------------------------
    public function test_unjsonize()
    {
        $ser  = self::file_get_contents('data/jsonize.ser');
        $json = trim(self::file_get_contents('data/jsonize.json'));

        $ser_ = str_replace("\n", "\r\n", $ser);

        $os  = hQueryTestSurrogate::unjsonize($ser);
        $os_ = hQueryTestSurrogate::unjsonize($ser_);
        $oj  = hQueryTestSurrogate::unjsonize($json);

        assertNotEmpty($os);
        assertNotEmpty($os_);
        assertNotEmpty($oj);
        assertEquals($os, $oj);

        $t = hQueryTestSurrogate::unjsonize('{"a":1,"b":2,}');
        assertNotEmpty($t, 'should handle trailing commas in objects');
        $t = hQueryTestSurrogate::unjsonize('[1,2,]');
        assertNotEmpty($t, 'should handle trailing commas in arrays');

        return array($os, $ser, $json);
    }

    /**
     * @depends test_unjsonize
     */
     #[Depends('test_unjsonize')]
    public function test_jsonize($vars)
    {
        list($o, $ser, $json) = $vars;

        $b = hQueryTestSurrogate::jsonize($o);

        assertTrue(is_string($b));
        assertNotEmpty($b);

        assertTrue($b == $json || $b == $ser);
    }

    // -----------------------------------------------------
}
