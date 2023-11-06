<?php declare(strict_types=1);

/*******************************************************************************
*                                                                              *
*   Asinius\Document\HTML\Writer                                               *
*                                                                              *
*   This class returns an object that can accept a DOMNode, DOMDocument,       *
*   DOMElement, or similar object, and return well-formed HTML for it and      *
*   all of its descendants.                                                    *
*                                                                              *
*   Depending on the options passed to it, it can also sanitize the output     *
*   (making it safe to store or return user input).                            *
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


namespace Asinius\Document\HTML;

use Asinius\Document\HTML;
use DOMElement;

class Writer
{

    //  Formatting flags used to tune the output for specific tags.
    //  Self-closing tags: <br/>, not <br></br>.
    private const   SELF_CLOSING_TAG       =  1;

    //  Tags that are expected to have no content. Usually the same as the
    //  self-closing tags (making them "void tags").
    private const   NO_CONTENT             =  2;

    //  If the writer is allowed to reformat the HTML, then this tag should
    //  start on a new line.
    private const   NEWLINE_BEFORE_TAG     =  4;

    //  If the writer is allowed to reformat the HTML, then this tag's content
    //  should start on a new line.
    private const   NEWLINE_BEFORE_CONTENT =  8;

    //  If the writer is allowed to reformat the HTML, then this tag's content
    //  should end on its own line (moving the closing tag to its own line).
    private const   NEWLINE_AFTER_CONTENT  = 16;

    //  If the writer is allowed to reformat the HTML, then this element's
    //  closing tag should force the next tag to the next line.
    private const   NEWLINE_AFTER_TAG      = 32;

    //  This is an inline element. Do none of the above.
    private const   INLINE_ELEMENT         =  0;

    //  This is a block-level element. Do most of the above.
    private const   BLOCK_ELEMENT          = self::NEWLINE_BEFORE_TAG | self::NEWLINE_BEFORE_CONTENT | self::NEWLINE_AFTER_CONTENT | self::NEWLINE_AFTER_TAG;

    //  A table of common elements and their formatting flags.
    private const   ELEMENTS = [
        '*'     => self::BLOCK_ELEMENT,
        'a'     => self::INLINE_ELEMENT,
        'b'     => self::INLINE_ELEMENT,
        'br'    => self::SELF_CLOSING_TAG | self::NO_CONTENT,
        'h1'    => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'h2'    => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'h3'    => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'h4'    => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'hr'    => self::SELF_CLOSING_TAG | self::NO_CONTENT | self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'i'     => self::INLINE_ELEMENT,
        'img'   => self::SELF_CLOSING_TAG | self::NO_CONTENT,
        'li'    => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'p'     => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'span'  => self::INLINE_ELEMENT,
        'u'     => self::INLINE_ELEMENT,
    ];

    //  The control options that were passed to the writer's constructor.
    //  Supported flags are found in the HTML class.
    private int     $flags;

    //  Indent for nested tags in the output.
    private string  $indent         = '';

    //  Line break used in the output. ("\n")
    private string  $line_break     = '';

    //  List of tags that are suppressed in the output (without their content).
    private array   $dangerous_tags = [];

    //  List of tags that are specifically allowed in the output.
    private array   $allowed_tags   = ['*'];


    /**
     * Return the HTML attributes for a given node as a string.
     *
     * @param  DOMElement   $node
     * @return string
     */
    private function _write_attributes (DOMElement $node): string
    {
        if ( $node->attributes->count() === 0 ) {
            return '';
        }
        if ( ($this->flags & HTML::MAKE_SAFE) !== HTML::MAKE_SAFE ) {
            //  No special handling required.
            return implode('', array_map(function($attribute){
                return ' ' . $attribute->name . ($attribute->value == null ? '' : '="' . $attribute->value . '"');
            }, iterator_to_array($node->attributes)));
        }
        //  If all the MAKE_SAFE bits are set, then null chars will be stripped.
        switch (strtolower($node->tagName)) {
            case 'a':
                foreach (['href', 'name'] as $attribute) {
                    if ( $node->hasAttribute($attribute) ) {
                        $value = $node->getAttribute($attribute);
                        switch ($attribute) {
                            case 'href':
                                if ( preg_match('|^(?P<proto>[a-z]*:)?/*|i', $value, $captures) === 1 ) {
                                    if ( empty($captures['proto']) || in_array(strtolower($captures['proto']), ['http:', 'https:', 'ftp:']) ) {
                                        return ' href="' . addslashes(str_replace('\0', '', $value)) . '"';
                                    }
                                }
                                return '';
                            case 'name':
                                return ' name="' . addslashes(str_replace('\0', '', $value)) . '"';
                        }
                    }
                }
                return '';
            case 'img':
                if ( $node->hasAttribute('src') ) {
                    return ' src="' . addslashes(str_replace('\0', '', $node->getAttribute('src'))) . '"';
                }
                return '';

        }
        return '';
    }


    /**
     * Return the contents of a text node, optionally encoding any entities within it.
     *
     * @param  string       $text
     * @return string
     */
    private function _write_text (string $text): string
    {
        if ( $this->flags & HTML::ENCODE_ENTITIES ) {
            if ( ($this->flags & HTML::MAKE_SAFE) === HTML::MAKE_SAFE ) {
                return htmlentities(str_replace('\0', '', $text), ENT_QUOTES | ENT_DISALLOWED | ENT_HTML5, 'UTF-8', false);
            }
            return htmlentities($text, ENT_QUOTES | ENT_DISALLOWED | ENT_HTML5, 'UTF-8', false);
        }
        return $text;
    }


    /**
     * Return the HTML for a given tag, attributes, and content, using self::ELEMENTS
     * to control the formatting for the tag.
     *
     * @param  string       $orig_tag
     * @param  string       $attributes
     * @param  string       $content
     * @return string
     */
    private function _write_element (string $orig_tag, string $attributes, string $content): string
    {
        $formatting = static::ELEMENTS[$tag = strtolower($orig_tag)] ?? static::ELEMENTS['*'];
        if ( isset($this->dangerous_tags[$tag]) ) {
            return '';
        }
        if ( ! ($this->flags & HTML::REFORMAT_HTML) ) {
            return sprintf('<%s%s%s>%s%s', $orig_tag, $attributes, $formatting & static::SELF_CLOSING_TAG ? '/' : '', $formatting & static::NO_CONTENT ? '' : $content, $formatting & static::SELF_CLOSING_TAG ? '' : "</$orig_tag>");
        }
        $out = sprintf('%s<%s%s%s>', ($formatting & static::NEWLINE_BEFORE_TAG) ? $this->line_break : '', $tag, $attributes, ($formatting & static::SELF_CLOSING_TAG) ? '/' : '');
        if ( ! ($formatting & static::NO_CONTENT) &&  (trim($content) !== '') ) {
            if ( $formatting & static::NEWLINE_BEFORE_CONTENT ) {
                $content = $this->line_break . implode($this->line_break, array_map(fn($line) => $this->indent . $line, explode($this->line_break, $content)));
            }
            $out .= $content . (($formatting & static::NEWLINE_AFTER_CONTENT) ? $this->line_break : '');
        }
        if ( ! ($formatting & static::SELF_CLOSING_TAG) ) {
            $out .= "</$tag>";
        }
        if ( $formatting & static::NEWLINE_AFTER_TAG ) {
            $out .= $this->line_break;
        }
        return $out;
    }


    /**
     * Return the HTML for a given node and its descendants.
     *
     * @param  mixed        $node
     * @return string
     */
    private function _write_node ($node): string
    {
        if ( is_a($node, 'DOMNodeList') ) {
            return implode('', array_map(fn($node) => $this->_write_node($node), iterator_to_array($node)));
        }
        if ( $node->nodeType === XML_TEXT_NODE ) {
            return $this->_write_text($node->textContent);
        }
        if ( $node->nodeType === XML_DOCUMENT_NODE || $node->nodeType === XML_HTML_DOCUMENT_NODE ) {
            return implode('', array_map(fn($child) => $this->_write_node($child), iterator_to_array($node->childNodes)));
        }
        if ( $node->nodeType === XML_ELEMENT_NODE ) {
            $tag = strtolower($node->tagName);
            $content = implode('', array_map(fn($child) => $this->_write_node($child), iterator_to_array($node->childNodes)));
            if ( ! isset($this->allowed_tags['*']) && ! isset($this->allowed_tags[$tag]) ) {
                return $content;
            }
            $attributes = $this->_write_attributes($node);
            return $this->_write_element($node->tagName, $attributes, $content);
        }
        return '';
    }


    /**
     * Return a new writer pre-configured with options for controlling the output.
     *
     * @param  array        $options
     */
    public function __construct (array $options)
    {
        $this->flags = $options['flags'] ?? 0;
        if ( $this->flags & HTML::STRIP_DANGEROUS_TAGS ) {
            $this->dangerous_tags = $options['dangerous_tags'] ?? [];
        }
        $this->dangerous_tags = array_fill_keys(array_map('strtolower', $this->dangerous_tags), true);
        if ( $this->flags & HTML::ALLOWED_TAGS_ONLY ) {
            $this->allowed_tags = $options['allowed_tags'] ?? ['*'];
        }
        $this->allowed_tags = array_fill_keys(array_map('strtolower', $this->allowed_tags), true);
        if ( $this->flags & HTML::REFORMAT_HTML ) {
            $this->indent     = $options['indent'] ?? '    ';
            $this->line_break = "\n";
        }
    }


    /**
     * Return (optionally) formatted HTML for a given node and all of its
     * descendants.
     *
     * @param  mixed        $node
     * @return string
     */
    public function node_to_html ($node): string
    {
        if ( $node === null ) {
            return '';
        }
        $out = $this->_write_node($node);
        if ( $this->line_break !== '' ) {
            $out = implode($this->line_break, array_filter(explode($this->line_break, $out), fn($line) => strlen(trim($line))));
        }
        return $out . $this->line_break;
    }

}