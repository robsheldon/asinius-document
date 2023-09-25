<?php declare(strict_types=1);

/*******************************************************************************
*                                                                              *
*   Asinius\Document\HTML                                                      *
*                                                                              *
*   Static class with import/export functions for HTML<->Document conversions. *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2022 Rob Sheldon <rob@robsheldon.com>                        *
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

    const   UNSAFE_HTML        = 128;
    const   INCLUDE_TEXT_NODES = 1;
    const   TIDY               = 2;

    private $_document = null;
    private $_elements = null;


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
            $this->_document->strictErrorChecking = false;
            $this->_document->loadHTML($this->_data, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
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


    public function to_html ($options = 0): string
    {
        $html = $this->_document->saveXML();
        if ( $options & static::TIDY && class_exists('\\tidy') ) {
            //  TODO: Should log an error (but not throw) if the class doesn't exist.
            //    Maybe use error_log() or some wrapper around it in Asinius core?
            $tidy = new \tidy;
            $tidy->parseString($html, ['indent' => true, 'output-xhtml' => false, 'wrap' => 0], 'utf8');
            $html = tidy_get_output($tidy);
        }
        return $html;
    }

}
