<?php
use duzun\hQuery;
use GuzzleHttp\Client;

    // Read $url and $sel from request ($_POST | $_GET)
    $url = @$_POST['url'] ?: @$_GET['url'];
    $sel = @$_POST['sel'] ?: @$_GET['sel'];
    $go  = @$_POST['go']  ?: @$_GET['go'];

    $rm = strtoupper(getenv('REQUEST_METHOD') ?: $_SERVER['REQUEST_METHOD']);
    if ( $rm == 'POST' ) {
        require_once __DIR__ . '/../hquery.php';
        require_once __DIR__ . '/vendor/autoload.php';

        $config = [
            'timeout' => 7,
            // 'proxy' => [
            //     'http'  => 'tcp://localhost:8125', // Use this proxy with "http"
            //     'https' => 'tcp://localhost:9124', // Use this proxy with "https",
            //     'no' => ['.mit.edu', 'foo.com']    // Don't use a proxy with these
            // ],
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Encoding' => 'gzip',
            ],
        ];

        // Results acumulator
        $return = array();

        // If we have $url to parse and $sel (selector) to fetch, we a good to go
        if($url && $sel) {
            try {
                $client = new Client($config);

                $read_time = microtime(true);
                $response = $client->request('GET', $url);
                $read_time = (microtime(true) - $read_time) * 1e3;
                $doc = hQuery::fromHTML($response, $url);

                if($doc) {
                    // Read some meta info from $doc
                    $t = $doc->find('head title') and $t = trim($t->text()) and $meta['title'] = $t;
                    $t = $doc->find('head meta');
                    if ( $t ) foreach($t as $k => $v) {
                        switch($v->attr('name')) {
                            case 'description': {
                                $t = trim($v->attr('content')) and $meta['description'] = $t;
                            } break;
                            case 'keywords': {
                                $t = trim($v->attr('content')) and $meta['keywords'] = $t;
                            } break;
                        }
                    }
                    if ( $t = $doc->headers ) {
                        $b = array();
                        foreach($t as $k => $v) $b[$k] = "$k: " . (is_array($v) ? implode(PHP_EOL, $v) : $v);
                        $meta['headers'] = $b = implode(PHP_EOL, $b);
                    }
                    $select_time = microtime(true);
                    $elements = $doc->find($sel);
                    $select_time = microtime(true) - $select_time;
                    $return['select_time'] = $select_time;
                    $return['elements_count'] = count($elements);
                }
                else {
                    $return['request'] = hQuery::$last_http_result;
                }
            }
            catch(Exception $ex) {
                $error = $ex;
            }
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf8" />
    <title>hQuery playground example</title>
    <style lang="css">
        * {
            box-sizing: border-box;
        }
        html, body {
            position: relative;
            min-height: 100%;
        }
        header, section {
            margin: 10px auto;
            padding: 10px;
            width: 90%;
            max-width: 1200px;
            border: 1px solid #eaeaea;
        }

        input {
            width: 100%;
        }
    </style>
</head>
<body>
    <header class="selector">
        <form name="hquery" action="" method="post">
            <p><label>URL: <input type="url" name="url" value="<?=htmlspecialchars(@$url, ENT_QUOTES);?>" placeholder="ex. https://mariauzun.com/portfolio" autofocus class="form-control" /></label></p>
            <p><label>Selector: <input type="text" name="sel" value="<?=htmlspecialchars(@$sel, ENT_QUOTES);?>" placeholder="ex. 'a[href] &gt; img[src]:parent'" class="form-control" /></label></p>

            <p>
                <button type="submit" name="go" value="elements" class="btn btn-success">Fetch elements</button>
                <button type="submit" name="go" value="meta" class="btn btn-success">Fetch meta</button>
                <button type="submit" name="go" value="source" class="btn btn-success">Fetch source</button>
            </p>

            <?php if( !empty($error) ): ?>
            <div class="error">
                <h3>Error:</h3>
                <p>
                    <?=$error->getMessage();?>
                </p>
            </div>
            <?php endif; ?>
        </form>
    </header>

    <section class="result">
        <?php switch ($go) {
            case 'elements': if(!empty($elements)):?>
                <hr />
                <table style="width: 100%">
                    <thead><tr>
                        <th>pos.</th>
                        <th>html</th>
                        <th>view</th>
                    </tr></thead>
                    <tbody>
            <?php foreach($elements as $pos => $el): ?>
                        <tr>
                            <td><i class="col-xs-1"><?=$pos;?></i></td>
                            <td><code style="word-break:break-word;"><?=htmlspecialchars($el->outerHtml(), ENT_QUOTES);?></code>&nbsp;</td>
                            <td><?=$el->outerHtml();?></td>
                        </tr>
            <?php endforeach;?>
                    </tbody>
                </table>
            <?php
            endif;
            break;

            case 'meta':?>
                <ul class="list-group">
                    <li class="list-group-item">
                        Size: <span data-name="doc.size" class="badge"><?=empty($doc)?'':$doc->size;?></span>
                        <br />
                    </li>
                    <li class="list-group-item">Read Time: <span class="badge"><span data-name="doc.read_time"><?php echo $doc->read_time ?: $read_time?></span> ms</span><br /></li>
                    <li class="list-group-item">Index Time: <span class="badge"><span data-name="doc.index_time"><?php echo $doc->index_time?></span> ms</span><br /></li>
                    <li class="list-group-item">
                        Charset: <span data-name="doc.charset" class="badge"><?=empty($doc)?'':$doc->charset;?></span>
                        <br />
                    </li>
                    <li class="list-group-item">
                        Base URL: <span data-name="doc.base_url" class="badge"><?=empty($doc)?'':$doc->base_url;?></span>
                        <br />
                    </li>
                    <li class="list-group-item">
                        Href: <span data-name="doc.href" class="badge"><?=empty($doc)?'':$doc->href;?></span>
                        <br />
                    </li>
                    <li class="list-group-item">
                        Title: <span data-name="doc.title" class="badge"><?=empty($meta['title'])?'':$meta['title'];?></span>
                        <br />
                    </li>
                    <li class="list-group-item">
                        Description: <span data-name="doc.description" class="badge"><?=empty($meta['description'])?'':$meta['description'];?></span>
                        <br />
                    </li>
                    <li class="list-group-item">
                        Keywords: <span data-name="doc.keywords" class="badge"><?=empty($meta['keywords'])?'':$meta['keywords'];?></span>
                        <br />
                    </li>
                    <li class="list-group-item">
                        HTTP Headers: <pre data-name="doc.headers"><?=empty($meta['headers'])?'':$meta['headers'];?></pre>
                    </li>
                </ul>
            <?php
            break;

            case 'source':?>
                <pre><?=empty($doc)?'':htmlspecialchars($doc->html(), ENT_QUOTES);?></pre>
            <?php
            break;
        } ?>
    </section>
</body>
</html>
