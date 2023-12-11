<?php
    // Read $url and $sel from request ($_POST | $_GET)
    $url = @$_POST['url'] ?: @$_GET['url'];
    $sel = @$_POST['sel'] ?: @$_GET['sel'];
    $go  = @$_POST['go']  ?: @$_GET['go'];
    $rm = strtoupper(getenv('REQUEST_METHOD') ?: $_SERVER['REQUEST_METHOD']);
    // var_export(compact('url', 'sel', 'go')+[$rm]+$_SERVER);
    if ( $rm == 'POST' ) {
        require_once __DIR__ . '/../hquery.php';

        $config = [
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36',
            'accept_html' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        ];

        // Enable cache
        hQuery::$cache_path = sys_get_temp_dir() . '/hQuery/';
        hQuery::$cache_expires = (int) $_POST['ch'];

        // Results acumulator
        $return = array();

        // If we have $url to parse and $sel (selector) to fetch, we a good to go
        if($url && $sel) {
            try {
                $doc = hQuery::fromUrl(
                    $url
                  , [
                        'Accept'     => $config['accept_html'],
                        'User-Agent' => $config['user_agent'],
                        'Upgrade-Insecure-Requests' => 1,
                    ]
                );
                if($doc) {
                    // Follow redirects
                    $t = $doc->href and $url = $t;

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
                    throw new Exception("HTTP status code {$return['request']->code}");
                }
            }
            catch(Exception $err) {
                $error = $err;
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
        *, *:before, *:after {
            -webkit-box-sizing: border-box;
            box-sizing: border-box;
        }
        html, body {
            position: relative;
            min-height: 100%;
            font-family: open sans,-apple-system,system-ui,BlinkMacSystemFont,segoe ui,roboto,helvetica neue,Arial,noto sans,sans-serif,apple color emoji,segoe ui emoji,segoe ui symbol,noto color emoji;
            font-size: 16px;
        }
        a {
            color: #428bca;
            text-decoration: none;
        }
        a:hover, a:focus {
            color: #2a6496;
            text-decoration: underline;
        }
        header, section {
            margin: 10px auto;
            padding: 10px;
            width: 90%;
            max-width: 1000px;
            border: 1px solid #eaeaea;
        }
        input:not([type="number"]) {
            width: 100%;
        }
        button[aria-pressed="true"] {
            background-color: #ccc;
            border-style: groove;
            border-color: #ccc;
        }
        code, kbd, pre, samp {
            font-family: monospace, serif;
            font-size: 1em;
        }
        code {
            padding: 3px 6px;
            font-size: 90%;
            background-color: #eff1f3;
            white-space: nowrap;
            border-radius: 4px;
        }
        pre {
            display: block;
            padding: 9.5px;
            margin: 0 0 10px;
            font-size: 13px;
            line-height: 1.428571429;
            word-break: break-all;
            word-wrap: break-word;
            color: #333;
            background-color: #f5f5f5;
            white-space: pre-wrap;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        pre code {
            padding: 0;
            font-size: inherit;
            color: inherit;
            white-space: pre-wrap;
            background-color: transparent;
            border-radius: 0;
        }
        .pre-scrollable {
            max-height: 340px;
            overflow-y: scroll;
        }

        table>thead>tr>th {
            padding-bottom: 15px;
            text-align: left;
            border-bottom: 1px solid #eaeaea;
        }
        table>tbody>tr>td {
            padding: 15px 5px 10px;
            vertical-align: top;
        }
        td pre {
            margin: 0;
        }

        .list-group {
            margin-bottom: 20px;
            padding-left: 0
        }
        .list-group-item {
            position: relative;
            display: block;
            padding: 10px 15px;
            margin-bottom: -1px;
            background-color: #fff;
            border: 1px solid #ddd;
        }
        .list-group-item:first-child {
            border-top-right-radius: 4px;
            border-top-left-radius: 4px
        }
        .list-group-item:last-child {
            margin-bottom: 0;
            border-bottom-right-radius: 4px;
            border-bottom-left-radius: 4px;
        }
        .list-group-item>.badge {
            float: right;
            max-width: 85%;
            white-space: normal;
            text-align: right;
        }
        .list-group-item>.badge+.badge {
            margin-right: 5px;
        }
        a.list-group-item {
            color: #555;
        }
        a.list-group-item .list-group-item-heading {
            color: #333;
        }
        a.list-group-item:hover, a.list-group-item:focus {
            text-decoration: none;
            background-color: #f5f5f5;
        }
        a.list-group-item.active,
        a.list-group-item.active:hover,
        a.list-group-item.active:focus {
            z-index: 2;
            color: #fff;
            background-color: #428bca;
            border-color: #428bca;
        }
        a.list-group-item.active .list-group-item-heading,
        a.list-group-item.active:hover .list-group-item-heading,
        a.list-group-item.active:focus .list-group-item-heading {
            color: inherit;
        }
        a.list-group-item.active .list-group-item-text,
        a.list-group-item.active:hover .list-group-item-text,
        a.list-group-item.active:focus .list-group-item-text {
            color: #e1edf7;
        }
        .list-group-item-heading {
            margin-top: 0;
            margin-bottom: 5px;
        }
        .list-group-item-text {
            margin-bottom: 0;
            line-height: 1.3;
        }

        .badge {
            display: inline-block;
            min-width: 10px;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: bold;
            color: #333;
            /* line-height: 1; */
            vertical-align: baseline;
            white-space: nowrap;
            text-align: center;
            background-color: #f5f5f5;
            border: 1px solid #ccc;
            border-radius: 10px;
        }
        .badge:empty {
            display: none;
        }
        a.badge:hover, a.badge:focus {
            color: #333;
            /* text-decoration: none; */
            cursor: pointer;
        }
        a.list-group-item.active>.badge {
            color: #428bca;
            background-color: #fff;
        }
        
        .text-center {
            text-align: center;
        }

        .error {
            color: #c7254e;
        }
        .error pre.err {
            color: #c7254e;
            background-color: #f9f2f4;
            border-color: #c7254e;
        }
    </style>
</head>
<body>
    <header class="selector">
        <div class="head text-center">
			<h1><a href="https://github.com/duzun/hQuery.php" target="_blank">hQuery.php - fast HTML parser</a></h1>
        </div>
        <form name="hquery" action="" method="post">
            <p><label>URL: <input type="url" name="url" value="<?=htmlspecialchars(@$url, ENT_QUOTES);?>" placeholder="e.g. https://mariauzun.com/portfolio" autofocus class="form-control" required /></label></p>
            <p><label>Selector: <input type="text" name="sel" value="<?=htmlspecialchars(@$sel, ENT_QUOTES);?>" placeholder="e.g. 'a[href] &gt; img[src]:parent'" class="form-control" required /></label></p>
            <p><label>Cache: <input type="number" name="ch" value="<?=$_POST['ch']>=0?@$_POST['ch']:1800;?>" min="0" max="3600" placeholder="e.g. 1800" class="form-control" /> (seconds)</label></p>

            <p>
                <button type="submit" name="go" value="elements" <?=$go=='elements'?'aria-pressed="true"':''?> class="btn btn-success">Fetch elements</button>
                <button type="submit" name="go" value="meta" <?=$go=='meta'?'aria-pressed="true"':''?> class="btn btn-success">Fetch meta</button>
                <button type="submit" name="go" value="source" <?=$go=='source'?'aria-pressed="true"':''?> class="btn btn-success">Fetch source</button>
            </p>

            <?php if( !empty($error) ):?>
            <div class="error">
                <h3>Error:</h3>
                <pre class="err"><?=$error->getMessage();?></pre>
            </div>
            <?php endif; ?>
        </form>
    </header>

    <section class="result">
        <?php switch ($go) {
            case 'elements': if( !empty($elements) ):?>
                <table style="width: 100%">
                    <thead><tr>
                        <th>Pos.</th>
                        <th>HTML</th>
                        <th>View</th>
                    </tr></thead>
                    <tbody>
            <?php foreach($elements as $pos => $el): ?>
                        <tr>
                            <td><i class="col-xs-1"><?=$pos;?></i></td>
                            <td><pre style="word-break:break-word;"><?=htmlspecialchars($el->outerHtml(), ENT_QUOTES);?></pre></td>
                            <td><?=$el->outerHtml();?></td>
                        </tr>
            <?php endforeach;?>
                    </tbody>
                </table>
            <?php
            endif;
            break;

            case 'meta': if( !empty($doc) ):?>
                <ul class="list-group">
                    <li class="list-group-item">
                        hQuery::$cache_path: <code><?php echo hQuery::$cache_path ?></code>
                    </li>
                    <li class="list-group-item">
                        Size: <span data-name="doc.size" class="badge"><?=empty($doc)?'':$doc->size;?></span>
                        <div style="clear: both;"></div>
                    </li>
                    <li class="list-group-item">Read Time: <span class="badge"><span data-name="doc.read_time"><?php echo $doc->read_time?></span> ms</span><br /></li>
                    <li class="list-group-item">Index Time: <span class="badge"><span data-name="doc.index_time"><?php echo $doc->index_time?></span> ms</span><br /></li>
                    <li class="list-group-item">
                        Charset: <span data-name="doc.charset" class="badge"><?=empty($doc)?'':$doc->charset;?></span>
                        <div style="clear: both;"></div>
                    </li>
                    <li class="list-group-item">
                        Base URL: <a href="<?=empty($doc)?'':$doc->base_url;?>" target="_blank" rel="nofollow" data-name="doc.base_url" class="badge"><?=empty($doc)?'':$doc->base_url;?></a>
                        <div style="clear: both;"></div>
                    </li>
                    <li class="list-group-item">
                        Href: <a href="<?=empty($doc)?'':$doc->href;?>" target="_blank" rel="nofollow" data-name="doc.href" class="badge"><?=empty($doc)?'':$doc->href;?></a>
                        <div style="clear: both;"></div>
                    </li>
                    <li class="list-group-item">
                        Title: <span data-name="doc.title" class="badge"><?=empty($meta['title'])?'':$meta['title'];?></span>
                        <div style="clear: both;"></div>
                    </li>
                    <li class="list-group-item">
                        Description: <span data-name="doc.description" class="badge"><?=empty($meta['description'])?'':$meta['description'];?></span>
                        <div style="clear: both;"></div>
                    </li>
                    <li class="list-group-item">
                        Keywords: <span data-name="doc.keywords" class="badge"><?=empty($meta['keywords'])?'':$meta['keywords'];?></span>
                        <div style="clear: both;"></div>
                    </li>
                    <li class="list-group-item">
                        HTTP Headers: <pre data-name="doc.headers"><?=empty($meta['headers'])?'':$meta['headers'];?></pre>
                    </li>
                </ul>
            <?php elseif (!empty($return)):?>
                <pre><?=json_encode($return['request']->headers, JSON_PRETTY_PRINT);?></pre>
            <?php
            endif;
            break;

            case 'source':?>
                <pre><?=htmlspecialchars(empty($doc)?(empty($return)?'':$return['request']->body):$doc->html(), ENT_QUOTES);?></pre>
            <?php
            break;
        } ?>
    </section>
</body>
</html>
