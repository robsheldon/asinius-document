<?php declare(strict_types=1);

/*******************************************************************************
*                                                                              *
*   Asinius\Document\Markdown                                                  *
*                                                                              *
*   Static class with import/export functions for Markdown<->Document          *
*   conversions.                                                               *
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

use RuntimeException;
use Asinius\Document;


class Markdown extends Document
{

    private const   PARAGRAPH                =   0;
    private const   HEADING                  =  60;
    private const   HORIZONTAL_RULE          =  70;
    private const   CODE_BLOCK               =  80;
    private const   LIST                     =  90;
    private const   LIST_ORDERED_NUMERIC     =  92;
    private const   LIST_ORDERED_ROMAN_LOWER =  93;
    private const   LIST_ORDERED_ROMAN_UPPER =  94;
    private const   LIST_ORDERED_ALPHA_LOWER =  95;
    private const   LIST_ORDERED_ALPHA_UPPER =  96;
    private const   LIST_CHECKLIST           =  97;
    private const   LIST_UNORDERED           =  99;
    private const   BLOCKQUOTE               = 100;

    private const   BLOCK_ELEMENTS = [
        self::HEADING         => '/^#{1,6}\s/',
        self::HORIZONTAL_RULE => '/^---\s*$/',
        self::CODE_BLOCK      => '/^```(?P<language>[a-zA-Z0-9-]*)$/',
        self::LIST            => '/^(?P<indent>\s*)(?P<list_symbol>[*+]|[a-zA-Z]+\.|[0-9]+\.|\[[xX ]?\])\s+(?P<list_item>.+)$/',
        self::BLOCKQUOTE      => '/^\s*>\s*(?P<quoted>.*)/',
    ];

    private const   STYLE_PATTERNS = [
        '/\*{3}(.+)\*{3}/'                                        => '<b><i>$1</i></b>',
        '/(?<!\*|\w)\*{2}([^*\s]([^*]*[^*\s])?)\*{2}(?!\*|\w)/'   => '<b>$1</b>',
        '/(?<!\*|\w)([*_])([^*\s]([^*]*[^*\s])?)\1(?!\*|\w)/'     => '<i>$2</i>',
    ];

    private const   LIST_TYPE_PATTERNS = [
        self::LIST_ORDERED_NUMERIC     => '/^([0-9]+)\.$/',
        self::LIST_ORDERED_ROMAN_LOWER => '/^([ivxcm]+)\.$/',
        self::LIST_ORDERED_ROMAN_UPPER => '/^([IVXCM]+)\.$/',
        self::LIST_ORDERED_ALPHA_LOWER => '/^([a-z]+)\.$/',
        self::LIST_ORDERED_ALPHA_UPPER => '/^([A-Z]+)\.$/',
        self::LIST_CHECKLIST           => '/^(\[[Xx ]?\])$/',
        self::LIST_UNORDERED           => '/^([*+])$/',
    ];

    private ?HTML   $_html = null;


    /**
     * Read lines from $lines while they match a given pattern and return them.
     * Optionally, read the lines while they do not match a given pattern.
     *
     * @param  array    $lines
     * @param  string   $pattern
     * @param  bool     $match_pattern
     * @param  bool     $capture
     *
     * @return array
     */
    private function _readwhile (array &$lines, string $pattern, bool $match_pattern = true, bool $capture = false): array
    {
        $lines_out = [];
        while (($line = array_shift($lines)) !== null) {
            if ( (preg_match($pattern, $line, $matches) === 1) !== $match_pattern ) {
                array_unshift($lines, $line);
                break;
            }
            $lines_out[] = $capture ? $matches : $line;
        }
        return $lines_out;
    }


    private function _convert_styled_text (string $content): string
    {
        //  Translate simple inline markup.
        $content = preg_replace(array_keys(static::STYLE_PATTERNS), static::STYLE_PATTERNS, $content);
        //  Search for and reformat any code blocks.
        $content = preg_replace_callback('/(?<!`|\w)`(\s*)([^`]+?)(\s*)`(?!`|\w)/', function ($inline_code) {
            return '<code>' . str_replace(' ', '&nbsp;', $inline_code[1]) . htmlentities($inline_code[2], ENT_QUOTES | ENT_DISALLOWED | ENT_HTML401, 'UTF-8', false) . str_replace(' ', '&nbsp;', $inline_code[3]) . '</code>';
        }, $content);
        //  Search for and render any links.
        return preg_replace_callback('/\[([^\[\]"]+)\]\(([a-zA-Z0-9&%$?#@.=:\/_-]+)\)/', function ($link) {
            return sprintf('<a href="%s">%s</a>', $link[2], $link[1]);
        }, $content);
    }


    private function _generate_list (array &$lines): string
    {
        if ( count($lines) < 1 ) {
            return '';
        }
        //  Initialize the list according to the first element.
        $indent = strlen($lines[0]['indent'] ?? '');
        $tag = 'ul';
        $type_attribute = '';
        foreach (static::LIST_TYPE_PATTERNS as $list_type => $pattern) {
            if ( preg_match($pattern, $lines[0]['list_symbol']) === 1 ) {
                switch ( $list_type ) {
                    case static::LIST_CHECKLIST:
                        break 2;
                    case static::LIST_ORDERED_NUMERIC:
                        $tag = 'ol';
                        break 2;
                    case static::LIST_ORDERED_ROMAN_LOWER:
                        $tag = 'ol';
                        $type_attribute = ' type="i"';
                        break 2;
                    case static::LIST_ORDERED_ROMAN_UPPER:
                        $tag = 'ol';
                        $type_attribute = ' type="I"';
                        break 2;
                    case static::LIST_ORDERED_ALPHA_LOWER:
                        $tag = 'ol';
                        $type_attribute = ' type="a"';
                        break 2;
                    case static::LIST_ORDERED_ALPHA_UPPER:
                        $tag = 'ol';
                        $type_attribute = ' type="A"';
                        break 2;
                }
            }
        }
        $line_items = [];
        while (($line = array_shift($lines)) !== null) {
            $n = strlen($line['indent'] ?? '');
            if ( $n > $indent ) {
                //  Nested child list.
                array_unshift($lines, $line);
                $line_items[] = sprintf('<li>%s</li>', $this->_generate_list($lines));
                continue;
            }
            else if ( $n < $indent ) {
                //  This is a nested child list and we're returning to the
                //  parent list now.
                array_unshift($lines, $line);
                break;
            }
            if ( $list_type === static::LIST_ORDERED_NUMERIC && $line['list_symbol'] !== sprintf('%d.', count($line_items) + 1) ) {
                //  Allow users to renumber ordered numeric lists
                //  (but none of the others).
                $line_items[] = sprintf('<li value="%s">%s</li>', rtrim($line['list_symbol'], '.'), $line['list_item']);
            }
            else if ( $list_type === static::LIST_CHECKLIST ) {
                if ( preg_match('/^\[\s*\]$/', $line['list_symbol']) === 1 ) {
                    $line_items[] = sprintf('<li><input type="checkbox">%s</li>', $line['list_item']);
                }
                else {
                    $line_items[] = sprintf('<li><input type="checkbox" checked>%s</li>', $line['list_item']);
                }
            }
            else {
                $line_items[] = sprintf('<li>%s</li>', $line['list_item']);
            }
        }
        return sprintf('<%s%s>%s</%s>', $tag, $type_attribute, implode('', $line_items), $tag);
    }


    public function __construct ($data)
    {
        parent::__construct(trim($data));
        if ( ! is_string($this->_data) ) {
            throw new RuntimeException("Can't load contents of this data");
        }
    }


    public function mime_type (): string
    {
        return 'text/markdown';
    }


    public function to_html(): string
    {
        $this->_html = new HTML('<html><head></head><body></body></html>');
        $body = $this->_html->select('body');
        $lines = explode("\n", $this->_data);
        while (($line = array_shift($lines)) !== null) {
            $line = rtrim($line);
            if ( empty($line) ) {
                continue;
            }
            $matches = array_filter(static::BLOCK_ELEMENTS, function($pattern) use ($line, &$captured) {
                if ( preg_match($pattern, $line, $matches) === 1 ) {
                    $captured = $matches;
                    return true;
                }
                return false;
            });
            if ( count($matches) !== 1 ) {
                //  Default state: just spit out a paragraph.
                $body->append_html(sprintf('<p>%s</p>', $this->_convert_styled_text($line)));
                continue;
            }
            switch (key($matches)) {
                case static::HEADING:
                    $heading_level = strspn($line, '#');
                    $body->append_html(sprintf('<h%d>%s</h%d>', $heading_level, $this->_convert_styled_text(ltrim(substr($line, $heading_level))), $heading_level));
                    break;
                case static::HORIZONTAL_RULE:
                    if ( $body->childCount > 0 ) {
                        $body->append_html('<hr>');
                    }
                    else {
                        $frontmatter = $this->_readwhile($lines, static::BLOCK_ELEMENTS[static::HORIZONTAL_RULE], false);
                        //  Remove the closing '---'.
                        array_shift($lines);
                        $head = $this->_html->select('head');
                        foreach ($frontmatter as $line) {
                            if ( preg_match('/^\s*(?P<name>[a-zA-Z0-9_-]+):\s*(?P<content>.*)$/', $line, $captures) === 1 ) {
                                $head->append_html(sprintf('<meta name="%s" content="%s">', $captures['name'], htmlentities($captures['content'])));
                            }
                            else {
                                $head->append_html(sprintf('<meta content="%s">', htmlentities(trim($line))));
                            }
                        }
                    }
                    break;
                case static::CODE_BLOCK:
                    if ( isset($captured['language']) && $captured['language'] != '' ) {
                        $language = " class=\"language-{$captured['language']}\"";
                    }
                    else {
                        $language = '';
                    }
                    $code_lines = $this->_readwhile($lines, static::BLOCK_ELEMENTS[static::CODE_BLOCK], false);
                    //  Remove the closing '---'.
                    array_shift($lines);
                    $body->append_html(sprintf('<pre><code%s>%s</pre></code>', $language, implode("\n", array_map('rtrim', $code_lines))));
                    break;
                case self::LIST:
                    $list_lines = array_merge([$captured], $this->_readwhile($lines, static::BLOCK_ELEMENTS[static::LIST], true, true));
                    if ( count($list_lines) < 2 ) {
                        //  Assume this should just be a paragraph. Lists should
                        //  have more than one line.
                        $body->append_html(sprintf('<p>%s</p>', $this->_convert_styled_text($list_lines[0][0])));
                        break;
                    }
                    $body->append_html($this->_generate_list($list_lines));
                    break;
                case self::BLOCKQUOTE:
                    $quoted_lines = array_column(array_merge([$captured], $this->_readwhile($lines, static::BLOCK_ELEMENTS[static::BLOCKQUOTE], true, true)), 'quoted');
                    $body->append_html(sprintf('<blockquote>%s</blockquote>', implode("\n", $quoted_lines)));
                    break;
            }
        }
        return $this->_html->to_html();
    }

}
