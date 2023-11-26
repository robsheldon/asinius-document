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

    //  Tags that are expected to have no content.
    //  https://developer.mozilla.org/en-US/docs/Glossary/Void_element
    //  For XHTML output, these tags are converted into self-closing tags.
    private const   VOID_ELEMENT           =  1;

    //  If the writer is allowed to reformat the HTML, then this tag should
    //  start on a new line.
    private const   NEWLINE_BEFORE_TAG     =  2;

    //  If the writer is allowed to reformat the HTML, then this tag's content
    //  should start on a new line.
    private const   NEWLINE_BEFORE_CONTENT =  4;

    //  If the writer is allowed to reformat the HTML, then this tag's content
    //  should end on its own line (moving the closing tag to its own line).
    private const   NEWLINE_AFTER_CONTENT  =  8;

    //  If the writer is allowed to reformat the HTML, then this element's
    //  closing tag should force the next tag to the next line.
    private const   NEWLINE_AFTER_TAG      = 16;

    //  This is an inline element. Do none of the above.
    private const   INLINE_ELEMENT         =  0;

    //  This is a block-level element. Do most of the above.
    private const   BLOCK_ELEMENT          = self::NEWLINE_BEFORE_TAG | self::NEWLINE_BEFORE_CONTENT | self::NEWLINE_AFTER_CONTENT | self::NEWLINE_AFTER_TAG;

    //  A table of common elements and their formatting flags.
    private const   ELEMENTS = [
        '*'      => self::BLOCK_ELEMENT,
        'a'      => self::INLINE_ELEMENT,
        'area'   => self::VOID_ELEMENT,
        'b'      => self::INLINE_ELEMENT,
        'base'   => self::VOID_ELEMENT,
        'br'     => self::VOID_ELEMENT,
        'code'   => self::INLINE_ELEMENT,
        'col'    => self::VOID_ELEMENT,
        'embed'  => self::VOID_ELEMENT,
        'h1'     => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'h2'     => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'h3'     => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'h4'     => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'hr'     => self::VOID_ELEMENT | self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'i'      => self::INLINE_ELEMENT,
        'img'    => self::VOID_ELEMENT,
        'input'  => self::VOID_ELEMENT,
        'li'     => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'link'   => self::VOID_ELEMENT | self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'meta'   => self::VOID_ELEMENT | self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'p'      => self::NEWLINE_BEFORE_TAG | self::NEWLINE_AFTER_TAG,
        'param'  => self::VOID_ELEMENT,
        'source' => self::VOID_ELEMENT,
        'span'   => self::INLINE_ELEMENT,
        'track'  => self::VOID_ELEMENT,
        'u'      => self::INLINE_ELEMENT,
        'wbr'    => self::VOID_ELEMENT,
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
                //  Sigh. Special case. PHP sets $attribute->value to "checked"
                //  for `<input type="checkbox" checked>`.
                if ( $attribute->ownerElement !== null && strtolower($attribute->ownerElement->tagName) === 'input' && $attribute->value === 'checked' ) {
                    return ' ' . $attribute->name;
                }
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
                return htmlentities(str_replace('\0', '', $text), ENT_QUOTES | ENT_DISALLOWED | ENT_HTML401, 'UTF-8', false);
            }
            return htmlentities($text, ENT_QUOTES | ENT_DISALLOWED | ENT_HTML401, 'UTF-8', false);
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
            if ( $this->flags & HTML::XHTML ) {
                return sprintf('<%s%s%s>%s%s',
                    $orig_tag,
                    $attributes,
                    $formatting & static::VOID_ELEMENT ? ' /' : '',
                    $formatting & static::VOID_ELEMENT ? '' : $content,
                    $formatting & static::VOID_ELEMENT ? '' : "</$orig_tag>"
                );
            }
            return sprintf('<%s%s>%s%s', $orig_tag, $attributes, $formatting & static::VOID_ELEMENT ? '' : $content, $formatting & static::VOID_ELEMENT ? '' : "</$orig_tag>");
        }
        $out = sprintf('%s<%s%s%s>', ($formatting & static::NEWLINE_BEFORE_TAG) ? $this->line_break : '', $tag, $attributes, (($formatting & static::VOID_ELEMENT) && ($this->flags & HTML::XHTML) ? ' /' : ''));
        if ( ! ($formatting & static::VOID_ELEMENT) && (trim($content) !== '') ) {
            if ( $formatting & static::NEWLINE_BEFORE_CONTENT ) {
                if ( $tag === 'pre' ) {
                    //  This is a tricky special case. `<pre><code>` is a kind
                    //  of meta-tag that signals a code block in html5.
                    //  So, look for a <code> tag and, if it exists, pull it up
                    //  to the same line, converting it into a block tag of sorts.
                    if ( preg_match('|^(<code[^>]*>)(.*?)(</code>)$|s', $content, $patterns) === 1 ) {
                        $content = $patterns[1] . $this->line_break . $patterns[2] . $this->line_break . $patterns[3];
                        $formatting ^= static::NEWLINE_AFTER_CONTENT;
                    }
                }
                else {
                    //  Indent child content.
                    $lines = explode($this->line_break, $content);
                    $n = count($lines);
                    for ( $i = 0; $i < $n; $i++ ) {
                        $lines[$i] = $this->indent . $lines[$i];
                        if ( preg_match('/^\s*<pre(\s+[^>])*>/', $lines[$i]) === 1 ) {
                            //  Do not indent lines inside <pre> tags.
                            $start = $i;
                            while ( $i < $n && preg_match('|</pre>$|', $lines[$i]) !== 1 ) {
                                $i++;
                            }
                            if ( $i !== $start ) {
                                //  But multi-line <pre> tags should have the closing tag indented.
                                $lines[$i] = $this->indent . $lines[$i];
                            }
                        }
                    }
                    $content = $this->line_break . implode($this->line_break, $lines);
                }
            }
            else if ( $tag === 'li' ) {
                //  Similar to <pre><code>, pull up nested lists into their parents.
                //  If there's another element pair like this, I should come up
                //  with a cleaner way of handling this.
                if ( preg_match('|^\s*(<[ou]l[^>]*>)(.*?)(</[ou]l>)\s*$|s', $content, $patterns) === 1 ) {
                    $content = $patterns[1] . $this->line_break . $patterns[2] . $this->line_break . $patterns[3];
                    $formatting &= ~static::NEWLINE_AFTER_CONTENT;
                }
            }
            $out .= $content . (($formatting & static::NEWLINE_AFTER_CONTENT) ? $this->line_break : '');
        }
        if ( ! ($formatting & static::VOID_ELEMENT) ) {
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