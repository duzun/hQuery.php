<?php
use duzun\hQuery;
use duzun\hQuery\Element;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Mock\Client;

// -----------------------------------------------------
/**
 *  @TODO: Test all methods
 *  @author DUzun.Me
 */
// -----------------------------------------------------
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '_PHPUnit_BaseClass.php';

// -----------------------------------------------------
// Surogate class for testing, to access protected attributes of hQuery
class TestHQueryTests extends hQuery
{
    /**
     * @var mixed
     */
    public $class_idx;

    /**
     * @param $str
     * @param $p
     */
    public static function html_findTagClose($str, $p)
    {
        return parent::html_findTagClose($str, $p);
    }
}
// -----------------------------------------------------

class TestHQuery extends PHPUnit_BaseClass
{
    // -----------------------------------------------------
    /**
     * @var TestHQueryTests
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
<doctype html>
<html>
<head>
    <meta charset="ISO-8859-2" />
    <!-- <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-2" /> -->
    <meta content="/logo.png" property="og:image" />
    <title>Sample HTML Doc</title>
    <link rel="shortcut icon" href="/favicon.ico" />
</head>
<body class="test-class">
    <div id="test-div" class="test-class test-div">
        text: This is some text
        <a href="/path">
            link: This is a link
        </a>
         in : between tags
        span: <span id="aSpan" class="span">Span text</span>
    </div>
    <a id="outterLink" href="//not-my-site.com/next.html">Not My Site</a>
    <img id="outterImg" src="https://cdn.duzun.me/images/logo.png" />

    <dl id="dict1">
      <dt>Coffee</dt>
      <dd>Black hot drink</dd>
      <dt>Milk</dt>
      <dd>White cold drink</dd>
    </dl>

    <table id="dict2">
        <tr>
            <th>Coffee</th>
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
EOS
    ;

    // Before any test
    public static function mySetUpBeforeClass()
    {
        hQuery::$_mockup_class = 'TestHQueryTests';

        self::$inst = TestHQueryTests::fromHTML(self::$bodyHTML, self::$baseUrl . 'index.html');

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
        $this->assertMehodExists('fromHTML', self::$className);
        $this->assertMehodExists('fromFile', self::$className);
        $this->assertMehodExists('fromURL', self::$className);

        // $f = glob(PHPUNIT_DIR . 'data/*');
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
        $this->assertEquals(self::$bodyHTML, $doc->html());
        $this->assertEquals(self::$baseUrl . 'index.html', $doc->location());

        // Optional stuff
        if (class_exists('Http\Discovery\MessageFactoryDiscovery')) {
            empty(self::$messageFactory) and self::$messageFactory = MessageFactoryDiscovery::find();

            // $response = $this->createMock('Psr\Http\Message\ResponseInterface');
            $response = self::$messageFactory->createResponse(
                '200',
                'ok',
                ['host' => parse_url(self::$baseUrl, PHP_URL_HOST), 'origin' => self::$baseUrl],
                self::$bodyHTML
            );

            // Document from a Psr\Http\Message\ResponseInterface object
            $doc = TestHQueryTests::fromHTML($response, $url);
            $this->assertEquals(self::$bodyHTML, $doc->html());
            $this->assertEquals($url, $doc->location());

            $request = self::$messageFactory->createRequest(
                'GET',
                $url,
                [],
                self::$bodyHTML
            );

            // Document from a Psr\Http\Message\RequestInterface object
            $doc = TestHQueryTests::fromHTML($request);
            $this->assertEquals(self::$bodyHTML, $doc->html());
            $this->assertEquals($url, $doc->location());
        } else {
            self::log('Http\Discovery\MessageFactoryDiscovery not found!');
        }

        if (class_exists('Http\Mock\Client')) {
            // Document from hQuery::sendRequest($request, $client)
            $client = new Client();
            $client->addResponse($response);

            $doc = TestHQueryTests::sendRequest($request, $client);
            $this->assertEquals(self::$bodyHTML, $doc->html());
            $this->assertEquals($url, $doc->location());
        } else {
            self::log('Http\Mock\Client not found!');
        }

        // And to make sure the $doc is realy ok
        $this->assertEquals('Sample HTML Doc', $doc->find('head title')->text);
    }

    // -----------------------------------------------------
    public function test_static_html_findTagClose()
    {
        // A string with misplaced quotes inside a tag
        $str1 = '<img class="map>Img" "src"="https://cdn.duzun.lh/images/logo.png"">
                 <div class="overlayLowlightoverlayBottom">abra-kadabra</div>
               ';
        $str2 = '<img "class"="mapImg" title="What <br>a nice day for testing!!!" ">
                 <div class="overlayLowlightoverlayBottom">abra-kadabra</div>
               ';
        $str3 = "<img 'class 4 mapImg' title='What <br>a nice day for testing!!' ''>
                 <div class='overlayLowlightoverlayBottom'>abra-kadabra</div>
               ";

        $r = TestHQueryTests::html_findTagClose($str1, 1);
        $this->assertEquals(66, $r);

        $r = TestHQueryTests::html_findTagClose($str2, 1);
        $this->assertEquals(66, $r);

        $r = TestHQueryTests::html_findTagClose($str3, 1);
        $this->assertEquals(66, $r);
    }

    // -----------------------------------------------------
    /**
     * @return mixed
     */
    public function test_find()
    {
        // 1)
        $a = self::$inst->find('.test-class #test-div.test-div > a');

        $this->assertNotEmpty($a);
        $this->assertTrue($a instanceof Element);
        $this->assertEquals('a', $a->nodeName);
        $this->assertEquals('link: This is a link', trim($a->text));
        $this->assertEquals('https://DUzun.Me/path', $a->attr('href'));
        $this->assertEquals('div', $a->parent->nodeName);
        $this->assertEquals('test-div', $a->parent->attr('id'));

        // 2)

        $ff = TestHQueryTests::fromFile(self::file_exists('data/attr.html'));
        $aa = $ff->find('a.aa');
        $this->assertEquals(3, count($aa));

        // self::log($ff->_info());

        // 3)
        $input = $ff->find('input');
        $this->assertEquals(3, count($input));

        $input = $ff->find('input', 'name=title');
        $this->assertEquals(1, count($input));
        $this->assertEquals('the title', $input->value);

        $input = $ff->find('input', ['type' => 'text']);
        $this->assertEquals(2, count($input));

        $input = $ff->find('input', ['type' => 'text', 'name' => 'text']);
        $this->assertEquals(1, count($input));
        $this->assertEquals('the text', $input->attr('value'));

        // @TODO
        // $input = $ff->find('input[name=title]');
        // $this->assertEquals(1, count($input));
        // $this->assertEquals('the title', $input->value);

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

        $this->assertNotEmpty($div->hasClass('test-class'), 'div.test-div should have .test-class class');
        $this->assertNotEmpty($div->hasClass(['test-class', 'test-div']), 'div.test-div should have .test-div and .test-class class');
        $this->assertEmpty($div->hasClass(['test-class', 'test-div', 'span']), 'div.test-div shouldn\'t have .no-class class');
        $this->assertNotEmpty($all->hasClass('test-class test-div'), 'At least one div should have .test-div and .test-class classes');

        $this->assertEmpty($a->hasClass('test-class'), 'a should have no class');
        $this->assertEmpty($body->hasClass('test-div'), 'body should not have "test-dev" class');
        $this->assertEmpty($body->hasClass('test-class test-div'), 'body doesn\'t have both classes .test-div and .test-class');

        // Some edge cases
        $this->assertEmpty($a->hasClass('non-existent-class'), 'non existent classes should not throw');
        $this->assertEmpty($head->hasClass('non-existent-class'), 'non existent classes should not throw even on elements with non attributes at all');
        $this->assertEmpty($div->hasClass(['non-existent-class', 'span']), 'non existent classes should not throw even when in combination with existing classes');
        $this->assertEmpty($a->hasClass(''), 'empty class doesn\'t exist');
        $this->assertEmpty($a->hasClass([]), 'empty class doesn\'t exist');
        $this->assertEmpty($empty->hasClass('test-class'), 'empty collection should not have any class');
        $this->assertEmpty($empty->hasClass('non-existent-class'), 'non existent classes should not throw even on empty collections');

        // More speciffic
        $this->assertEquals(0, $a->hasClass('test-class'), 'a should have no class');
        $this->assertEquals(false, $body->hasClass('test-div'), 'body should not have .test-div class');
        $this->assertEquals(true, $div->hasClass('test-div'), 'a should have .test-div class');
    }

    // -----------------------------------------------------
    /**
     * @depends test_find
     */
    public function test_hQuery_Element_ArrayAccess($doc)
    {
        $e = $doc->find('input');

        // Short forms of $e->get(0)->attr('name')
        $this->assertEquals('title', $e->get(0)->attr('name'));
        $this->assertEquals('title', $e[0]->name);
        $this->assertEquals('title', $e[0]['name']);
        $this->assertEquals('title', $e['name']);
        $this->assertEquals('title', $e->name);

        $this->assertEquals('text', $e[1]['name']);
        $this->assertEquals('random', $e[2]['name']);

        return $doc;
    }

    // -----------------------------------------------------
    /**
     * @depends test_hQuery_Element_ArrayAccess
     */
    public function test_attr($doc)
    {
        $e = $doc->find('#img1');

        // It's magic!
        $this->assertEquals($e->src, $e->attr('src'));
        $this->assertEquals($e->src1, $e->attr('src1'));
        $this->assertEquals($e->src2, $e->attr('src2'));

        // Standard way of accessing attributes:
        $this->assertEquals('/path/to/img.png', $e->attr('src'));
        $this->assertEquals('other/img/here.jpg', $e->attr('src2'));
        $this->assertEquals('//example.com/full/path.gif', $e->attr('src3'));

        // Relative vs Absolute URL paths

        // a[href] relative URL
        $a = self::$inst->find('a:first');
        $this->assertEquals(self::$baseUrl . 'path', $a->href);

        // a[href] absolute URL
        $a = self::$inst->find('a#outterLink');
        $this->assertEquals('https://not-my-site.com/next.html', $a->href);

        // img[src] absolute URL
        $a = self::$inst->find('img#outterImg');
        $this->assertEquals('https://cdn.duzun.me/images/logo.png', $a->src);

        // link[href] relative URL
        $a = self::$inst->find('link', ['rel' => 'shortcut icon']);
        $this->assertEquals(self::$baseUrl . 'favicon.ico', $a->href);

        // meta[content] - not a URL
        $m = self::$inst->find('meta', ['property' => 'og:image']);
        $c = $m->attr('content');

        $this->assertEquals('/logo.png', $c);

        $c = self::$inst->url2abs($c);
        $this->assertEquals(self::$baseUrl . 'logo.png', $c);

        return $doc;
    }

    // -----------------------------------------------------
    /**
     * @depends test_attr
     */
    public function test_prop_charset($doc)
    {
        $this->assertEquals('utf-8', strtolower($doc->charset));
        $this->assertEquals('iso-8859-2', strtolower(self::$inst->charset));

        return $doc;
    }

    // -----------------------------------------------------
    public function test_prop_size()
    {
        $size = self::$inst->size;
        $this->assertGreaterThan(200, $size);
    }

    // -----------------------------------------------------
    public function test_prop_baseURL()
    {
        $baseURL = self::$inst->baseURL;
        $this->assertEquals(self::$baseUrl, $baseURL);
    }

    // -----------------------------------------------------
    public function test_prop_baseURI()
    {
        $baseURI = self::$inst->baseURI;
        $this->assertEquals(self::$baseUrl . 'index.html', $baseURI);
    }

    // -----------------------------------------------------
    // Alias of baseURI
    public function test_prop_href()
    {
        $href = self::$inst->href;
        $this->assertEquals(self::$baseUrl . 'index.html', $href);
    }

    // -----------------------------------------------------
    public function test_text()
    {
        $div  = self::$inst->find('#test-div');
        $text = $div->text();

        $this->assertEquals("text: This is some text\n        \n            link: This is a link\n        \n         in : between tags\n        span: Span text", trim($text));
        $this->assertEquals('text: This is some text link: This is a link in : between tags span: Span text', preg_replace('/\\s+/', ' ', trim($text)));
    }

    public function test_text2dl()
    {
        $div = self::$inst->find('#test-div');

        // Fetch a definition list out of textContents
        $dl = $div->text2dl();
        $this->assertEquals([
            'text' => 'This is some text',
            'link' => 'This is a link',
            'in'   => 'between tags',
            'span' => 'Span text',
        ], $dl);

        // Fetch one value out of definition list as text
        $this->assertEquals('This is a link', $div->text2dl(':', 'link'));

        // Fetch one value out of definition list as text by filter function
        if (class_exists('Closure')) {
            $v = $div->text2dl(':', function ($key, $val) {
                return stripos($key, 'SPAN') !== false;
            });
            $this->assertEquals('Span text', $v);
        }
    }

    public function test_dl()
    {
        $dl = self::$inst->find('#dict1');

        // Fetch a definition list
        $dict = $dl->dl('dt', 'dd');
        $this->assertEquals([
            'Coffee' => 'Black hot drink',
            'Milk'   => 'White cold drink',
        ], $dict);

        // Fetch one value out of definition list
        $this->assertEquals('White cold drink', $dl->dl('dt', 'dd', null, 'Milk'));

        $dl = self::$inst->find('#dict2');

        // Fetch a definition list
        $dict = $dl->dl('th', 'td', 'tr');
        $this->assertEquals([
            'Coffee' => 'Black hot drink',
            'Milk'   => 'White cold drink',
        ], $dict);

        // Fetch one value out of definition list
        $this->assertEquals('White cold drink', $dl->dl('th', 'td', 'tr', 'Milk'));

        // @TODO
        // $dl = self::$inst->find('#dict3');

        // // Fetch a definition list
        // $dict = $dl->dl('b', NULL, 'span');
        // $this->assertEquals(array(
        //   'Coffee' => 'Black hot drink',
        //   'Milk'   => 'White cold drink',
        // ), $dict);

        // // Fetch one value out of definition list
        // $this->assertEquals('White cold drink', $dl->dl('th', 'td', 'tr', 'Milk'));

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
    //         $this->assertEquals('This is some text'.
    //                     '<a href="/path">'.
    //                         'This is a link'.
    //                     '</a>'.
    //                     ' between tags'.
    //                     '<span id="aSpan">Span text</span>', $html);
    //     }

    // -----------------------------------------------------
    // public function test_http_wr() {
    // @TODO
    // $doc = TestHQueryTests::fromURL('http://www.nameit.com', NULL, NULL, ['redirects' => 10, 'use_cookies' => true]);
    // self::log(gettype($doc));
    // self::log(TestHQueryTests::$last_http_result);
    // }

    // -----------------------------------------------------
    // -----------------------------------------------------
    public function test_unjsonize()
    {
        $ser  = self::file_get_contents('data/jsonize.ser');
        $json = self::file_get_contents('data/jsonize.json');

        $ser_ = str_replace("\n", "\r\n", $ser);

        $os  = TestHQueryTests::unjsonize($ser);
        $os_ = TestHQueryTests::unjsonize($ser_);
        $oj  = TestHQueryTests::unjsonize($json);

        $this->assertNotEmpty($os);
        $this->assertNotEmpty($os_);
        $this->assertNotEmpty($oj);
        $this->assertEquals($os, $oj);

        $t = TestHQueryTests::unjsonize('{"a":1,"b":2,}');
        $this->assertNotEmpty($t, 'should handle trailing commas in objects');
        $t = TestHQueryTests::unjsonize('[1,2,]');
        $this->assertNotEmpty($t, 'should handle trailing commas in arrays');

        return [$os, $ser, $json];
    }

    /**
     * @depends test_unjsonize
     */
    public function test_jsonize($vars)
    {
        list($o, $ser, $json) = $vars;
        $b                    = TestHQueryTests::jsonize($o);

        $this->assertTrue(is_string($b));
        $this->assertNotEmpty($b);

        $this->assertTrue($b == $json || $b == $ser);
    }

    // -----------------------------------------------------

}
