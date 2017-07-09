<?php
    /**
     * This is source code of https://duzun.me/playground/hquery
     * It is run inside a controller of my framework (thus $this),
     * and is not intended to be run as an independent example.
     */

    // Note: Autoloading handled by framework

    // Enable cache
    hQuery::$cache_path = sys_get_temp_dir() . '/hQuery/';

    // Results acumulator
    $return = array();

    // Read $url and $sel from request ($_GET | $_POST | JSON Payload)
    $url = $this->input->payload('url');
    $sel = $this->input->payload('sel');

    // Show only first 100Kb of HTML as a preview
    $max_show = 100 << 10;
    $max_show_unit = mem_unit($max_show, 1);

    // If we have $url to parse and $sel (selector) to fetch, we a good to go
    if($url && $sel) {
        try {
            $return['doc'] =
            $doc = hQuery::fromUrl(
                $url
              , array(
                    'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'User-Agent' => $this->config('user_agent'),
                )
            );
            if($doc) {
                $t = $doc->href and $return['url'] = $t;
                $t = $doc->find('head title') and $t = trim($t->text()) and $return['doc_title'] = $t;
                $t = $doc->find('head meta');
                if ( $t ) foreach($t as $k => $v) {
                    switch($v->attr('name')) {
                        case 'description': {
                            $t = trim($v->attr('content')) and $return['doc_description'] = $t;
                        } break;
                        case 'keywords': {
                            $t = trim($v->attr('content')) and $return['doc_keywords'] = $t;
                        } break;
                    }
                }
                if ( $t = $doc->headers ) {
                    $b = array();
                    foreach($t as $k => $v) $b[$k] = "$k: $v";
                    $return['doc_headers'] = $b = implode(PHP_EOL, $b);
                }
                $select_time = microtime(true);
                $elements = $doc->find($sel);
                $select_time = microtime(true) - $select_time;
                $return['select_time'] = $select_time;
                $return['elements'] = $elements;
                $big_doc = $doc->size > $max_show;
                $return['elements_count'] = count($elements);
            }
            else {
                $return['request'] = hQuery::$last_http_result;
            }
            if($this->output_type != 'html') {
                if($doc) {
                    $return['doc'] = array(
                        'charset'     => $doc->charset,
                        'size'        => $doc->size,
                        'base_url'    => $doc->base_url,
                        'html'        => $big_doc ? substr($doc->html(), 0, $max_show) : $doc->html(),
                        'read_time'   => round($doc->read_time),
                        'index_time'  => round($doc->index_time),
                        'source_type' => $doc->source_type,
                    );
                }

                foreach(array('title','keywords','description','headers') as $f) {
                    if(isset($return['doc_'.$f])) {
                        $return['doc'][$f] = $return['doc_'.$f];
                        unset($return['doc_'.$f]);
                    }
                }

                $els = array();
                if ( $elements ) foreach($elements as $pos => $el) {
                    $els[] = array(
                        'nodeName'  => $el->nodeName,
                        'attr'      => $el->attr(),
                        'outerHtml' => $el->outerHtml(),
                        'pos'       => $pos,
                    );
                }
                $return['elements'] = $els;
            }
        }
        catch(Exception $ex) {
            // Let framework handle the error
            $this->error_msg($ex);
        }

    }

    // Return result (output)
    return $return + compact('max_show', 'big_doc', 'max_show_unit');

// Note: output handled by framework (view or json)
