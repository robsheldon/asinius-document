<?php declare(strict_types=1);

/*******************************************************************************
*                                                                              *
*   Asinius\Document\HTML                                                      *
*                                                                              *
*   Static class with import/export functions for HTML<->Document conversions. *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2023 Rob Sheldon <rob@robsheldon.com>                        *
*                                                                              *
*   Permission is hereby granted, free of charge, to any person obtaining a    *
*   copy of this software and associated documentation files (the "Software"), *
*   to deal in the Software without restriction, including without limitation  *
*   the rights to use, copy, modify, merge, publish, distribute, sublicense,   *
*   and/or sell copies of the Software, and to permit persons to whom the      *
*   Software is furnished to do so, subject to the following conditions:       *
*                                                                              *
*   The above copyright notice and this permission notice shall be included    *
*   in all copies or substantial portions of the Software.                     *
*                                                                              *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS    *
*   OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF                 *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.     *
*   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY       *
*   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,       *
*   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE          *
*   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.                     *
*                                                                              *
*   https://opensource.org/licenses/MIT                                        *
*                                                                              *
*******************************************************************************/


namespace Asinius\Document;

use Generator;
use DOMDocument;
use Asinius\Document;
use Asinius\Document\HTML\Elements;
use Asinius\Document\HTML\Writer;
use RuntimeException;

if ( ! extension_loaded('dom') || ! class_exists('DOMDocument') ) {
    throw new RuntimeException(sprintf('%s requires the "dom" extension for PHP: https://www.php.net/manual/en/book.dom.php', 'Asinius\\Document'));
}

if ( ! extension_loaded('libxml') || ! function_exists('libxml_use_internal_errors') ) {
    throw new RuntimeException(sprintf('%s requires the "libxml" extension for PHP: https://www.php.net/manual/en/book.libxml.php', 'Asinius\\Document'));
}

//  Disable PHP's hyper-aggressive libxml error handler. Any XML-related errors
//  will be handled internally in the Document code.
libxml_use_internal_errors(true);


class HTML extends Document
{

    const   UNSAFE_HTML             = 1;
    const   INCLUDE_TEXT_NODES      = 2;
    const   REFORMAT_HTML           = 4;
    const   STRIP_DANGEROUS_TAGS    = 8;
    const   ALLOWED_TAGS_ONLY       = 16;
    const   ENCODE_ENTITIES         = 32;
    const   MAKE_SAFE               = self::STRIP_DANGEROUS_TAGS | self::ALLOWED_TAGS_ONLY | self::ENCODE_ENTITIES;


    private ?DOMDocument    $_document       = null;
    private ?Elements       $_elements       = null;
    private array           $_safe_tags      = [];
    private array           $_dangerous_tags = [];
    private string          $_indent         = '    ';
    private int             $_flags          = self::REFORMAT_HTML | self::ENCODE_ENTITIES;


    /**
     * @param string $selector
     * @return Generator<array>
     */
    public static function next_css_selector_component (string $selector): Generator
    {
        $_id       = '';
        $_class    = '';
        $_tag      = '';
        $_attrs    = [];
        $selector  = trim($selector);
        $n         = strlen($selector);
        for ($i = 0; $i < $n; $i++) {
            switch ($selector[$i]) {
                case '#':
                    $_id = substr($selector, ++$i, ($x = strpos($selector, ' ', $i) ?: $n) - $i);
                    $i = --$x;
                    break;
                case '.':
                    $_class = substr($selector, ++$i, ($x = strpos($selector, ' ', $i) ?: $n) - $i);
                    $i = --$x;
                    break;
                case '[':
                    //  Attribute selector.
                    if ( ($x = strpos($selector, ']', $i)) === false ) {
                        $i = strpos($selector, ' ', $i) - 1;
                    }
                    else {
                        $attribute = substr($selector, ++$i, $x - $i);
                        $i = $x;
                        //  See if the attribute selector specifies a value.
                        @list($attribute, $attribute_value) = explode('=', $attribute, 2);
                        $attribute_value = $attribute_value ?? '';
                        //  The last character of the attribute may be "|"
                        //  or "~" which modify the way that attributes match.
                        $modifier = strpbrk(substr($attribute, -1), '|~') ?: '';
                        //  Trim quotes.
                        if ( $attribute[0] === '"' && str_ends_with($attribute, '"')) {
                            $attribute = substr($attribute, 1, -1);
                        }
                        if ( strlen($attribute) > 0 ) {
                            if ( $attribute_value !== '' && $attribute_value[0] === '"' && str_ends_with($attribute_value, '"')) {
                                $attribute_value = substr($attribute_value, 1, -1);
                            }
                            //  The selection code later on will look for a modifier at the beginning of $attribute_value.
                            $_attrs[$attribute] = $modifier . $attribute_value;
                        }
                    }
                    break;
                case ' ':
                    yield ['tag' => strtolower($_tag), 'id' => $_id, 'class' => $_class, 'attributes' => $_attrs];
                    $_id       = '';
                    $_class    = '';
                    $_tag      = '';
                    $_attrs    = [];
                    break;
                default:
                    $_tag .= $selector[$i];
                    break;
            }
        }
        yield ['tag' => strtolower($_tag), 'id' => $_id, 'class' => $_class, 'attributes' => $_attrs];
    }


    public static function parse_css_selector (string $selector): array
    {
        return iterator_to_array(static::next_css_selector_component($selector));
    }


    public function __construct ($data)
    {
        parent::__construct($data);
         if ( is_a($this->_data, DOMDocument::class) ) {
            $this->_data->formatOutput        = true;
            $this->_data->preserveWhiteSpace  = false;
            $this->_data->strictErrorChecking = false;
            $this->_document = $this->_data;
        }
        else if ( is_string($this->_data) ) {
            $this->_document = new DOMDocument();
            $this->_document->formatOutput        = true;
            $this->_document->preserveWhiteSpace  = false;
            $this->_document->strictErrorChecking = false;
            //  Prefixing the input string with the xml tag fixes PHP's handling of
            //  certain symbols, like '…', which otherwise gets translated into something like
            //  "&amp;acirc;&amp;brvbar;", which is wrong and gross.
            $this->_document->loadHTML('<?xml encoding="UTF-8">' . $this->_data, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOXMLDECL);
        }
        else if ( is_object($this->_data) ) {
            throw new RuntimeException(sprintf("Can't parse HTML from this type of object: %s", get_class($this->_data)));
        }
        else {
            throw new RuntimeException(sprintf("Can't parse HTML from this type of object: %s", gettype($this->_data)));
        }
        //  https://stackoverflow.com/questions/8379829/domdocument-php-memory-leak
        //  https://www.php.net/manual/en/function.libxml-clear-errors.php
        libxml_clear_errors();
        $this->_elements = new Elements($this->_document->documentElement, $this->_document->documentElement, $this->_document);
    }


    public function mime_type (): string
    {
        return 'text/html';
    }


    public function select (string $selector): Elements
    {
        return $this->_elements->select($selector);
    }


    public function set_output_options (array $options)
    {
        if ( isset($options['flags']) ) {
            $this->_flags = $options['flags'];
            if ( ($this->_flags & static::STRIP_DANGEROUS_TAGS) && empty($this->_dangerous_tags) ) {
                $this->_dangerous_tags = ['script', 'style'];
            }
            if ( ($this->_flags & static::ALLOWED_TAGS_ONLY) && empty($this->_safe_tags) ) {
                $this->_safe_tags = ['h1', 'h2', 'h3', 'h4', 'p', 'a', 'i', 'u', 'b', 'ul', 'ol', 'li'];
            }
        }
        if ( isset($options['dangerous_tags']) ) {
            $this->_dangerous_tags = $options['dangerous_tags'];
        }
        if ( isset($options['allowed_tags']) ) {
            $this->_safe_tags = $options['allowed_tags'];
        }
        if ( isset($options['indent']) ) {
            $this->_indent = $options['indent'];
        }
    }


    public function to_html (?array $options = null): string
    {
        return (new Writer(['flags' => $this->_flags, 'dangerous_tags' => $this->_dangerous_tags, 'allowed_tags' => $this->_safe_tags, 'indent' => $this->_indent]))->node_to_html($this->_document);
        /*
        //  Sigh. It appears https://bugs.php.net/bug.php?id=47137 was never fixed.
        //  The next few lines work around this. See https://www.php.net/manual/en/libxml.constants.php#128713
        $html = new DOMDocument('1.0', 'UTF-8');
        $html->formatOutput        = true;
        $html->preserveWhiteSpace  = false;
        $html->strictErrorChecking = false;
        $html->loadXML($this->_document->saveXML());
        $html->normalizeDocument();
        $html = $html->saveXML($html->firstElementChild);
        //  Siiiiigh. This ALMOST works, but no. There's no built-in way to fix empty <head></head> tags without
        //  also breaking <br/> tags.
        //  https://www.php.net/manual/en/class.domdocument.php#104218 gives a good answer here. Can probably
        //  implement this with a push/pop stack instead of recursively calling back into a temporary function.
        //  Just use getElementsByTagName along with a list of tags, and set each tag's nodeValue to '' if it
        //  has no children. Easy peasy done.
        //  TODO
        return $html;
        */
    }

}
