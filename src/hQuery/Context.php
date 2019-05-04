<?php
namespace duzun\hQuery;

// ------------------------------------------------------------------------
class_exists('duzun\\hQuery\\Node', false) or require_once __DIR__ . DIRECTORY_SEPARATOR . 'Node.php';

// ------------------------------------------------------------------------
/**
 *  A context is a list of node addresses with reference to their document.
 *
 *  API Documentation at https://duzun.github.io/hQuery.php
 *
 *  @internal
 *  @license MIT
 */
class Context extends Node
{
    /**
     * @param duzun\hQuery            $doc
     * @param duzun\hQuery\Node|array $el_arr
     */
    public function __construct($doc = null, $el_arr = null)
    {
        if ($el_arr instanceof parent) {
            if (!$doc) {
                $doc = $el_arr->doc();
            }

            $el_arr = $el_arr->ids;
        } elseif (is_array($el_arr)) {
            ksort($el_arr);
        }

        parent::__construct($doc, $el_arr, true);
    }

    /**
     *  ctx($el) * $this
     *
     * @return duzun\hQuery\Context ctx
     */
    public function intersect($el, $eq = true)
    {
        if ($el instanceof self) {
            if ($el === $this) {
                if ($eq) {
                    return $this;
                } else {
                    $el = array();
                }

            } else {
                $el = $el->ids;
            }
        } else {
            $el = $this->_ctx_ids($this->_doc_ids($el, true));
        }

        foreach ($el as $b => $e) {
            if (!$this->_has($b, $eq)) {
                unset($el[$b]);
            }

        }

        return new self($el, $this->doc);
    }
}

// ------------------------------------------------------------------------
// PSR-0 alias
class_exists('hQuery_Context', false) or class_alias('duzun\\hQuery\\Context', 'hQuery_Context', false);
