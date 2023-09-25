<?php declare(strict_types=1);

/*******************************************************************************
*                                                                              *
*   Asinius\Document\Elements                                                  *
*                                                                              *
*   Encapsulation for working with sets of DOMNodes in a Document.             *
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
use DOMDocument, DOMElement, DOMNode;
use ArrayIterator;
use RuntimeException;


class Elements extends ArrayIterator
{

    protected ?DOMElement   $_root_node       = null;
    protected ?DOMDocument  $_parent_document = null;
    protected int           $_options         = 0;


    /**
     * Apply a function to each element in the current set and discard the output.
     *
     * @param mixed ...$arguments
     * @return  Elements
     * @internal
     *
     */
    protected function _for_all_do (...$arguments): Elements
    {
        $function = $arguments[0];
        $n = parent::count();
        for ( $i = 0; $i < $n; $i++ ) {
            $arguments[0] = parent::offsetGet($i);
            call_user_func_array($function, $arguments);
        }
        return $this;
    }


    /**
     * Create a new collection of html elements.
     *
     * @param   mixed               $elements
     * @param   DOMElement          $root_node
     * @param   DOMDocument         $parent_document
     * @param   integer             $options
     *
     * @throws  RuntimeException
     */
    public function __construct ($elements, DOMElement $root_node, DOMDocument $parent_document, int $options = 0)
    {
        if ( is_a($elements, 'DOMNode') ) {
            $elements = [$elements];
        }
        else if ( is_a($elements, 'DOMNodeList') ) {
            $elements = iterator_to_array($elements);
        }
        else if ( ! is_array($elements) ) {
            throw new RuntimeException('Not a DOMNode or DOMNodeList: $elements', EINVAL);
        }
        $this->_root_node       = $root_node;
        $this->_parent_document = $parent_document;
        $this->_options         = $options;
        parent::__construct($elements);
    }


    /**
     * Return the current element in the collection.
     *
     * @return  Elements|false
     */
    public function current (): Elements|false
    {
        if ( ($element = parent::current()) === false ) {
            return false;
        }
        return new Elements($element, $this->_root_node, $this->_parent_document, $this->_options);
    }


    /**
     * Get a copy of the current element collection.
     *
     * @return  Elements
     */
    public function getArrayCopy (): Elements
    {
        return new Elements(parent::getArrayCopy(), $this->_root_node, $this->_parent_document, $this->_options);
    }


    /**
     * Get an element at an index in the collection.
     *
     * @param   integer     $key
     *
     * @return  Elements|null
     */
    public function offsetGet ($key): ?Elements
    {
        $element = parent::offsetGet($key);
        if ( is_a($element, 'DOMNode') ) {
            return new Elements($element, $this->_root_node, $this->_parent_document, $this->_options);
        }
        return null;
    }


    /**
     * Set an element at an index in the collection.
     *
     * @param   integer     $key
     * @param   DOMNode     $value
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public function offsetSet ($key, $value): void
    {
        if ( ! is_a($value, 'DOMNode') ) {
            throw new RuntimeException('Not a DOMNode', EINVAL);
        }
        //  TODO. Need to figure out how to inject this element into the DOM
        //    (if it isn't already).
        throw new RuntimeException('Not implemented yet', ENOSYS);
    }


    /**
     * Delete an element from an index in the collection.
     *
     * @param   integer     $key
     *
     * @return  void
     */
    public function offsetUnset ($key): void
    {
        $element = parent::offsetGet($key);
        if ( is_a($element, 'DOMNOde') ) {
            $element->parentNode->removeChild($element);
        }
        parent::offsetUnset($key);
    }


    /**
     * Return the HTML for the current element collection.
     *
     * @return  string
     */
    public function __toString (): string
    {
        $document = new DOMDocument();
        $elements = parent::getArrayCopy();
        foreach ($elements as $element) {
            $document->appendChild($document->importNode($element, true));
        }
        return (new HTML($document))->to_html($this->_options);
    }


    /**
     * Return a new Elements object containing the set of all descendant elements
     * with a tag matching $tag. Use "*" to get all descendant elements.
     *
     * @param string $tag
     *
     * @return Elements
     */
    public function getElementsByTagName (string $tag): Elements
    {
        $elements = [];
        foreach (parent::getArrayCopy() as $element) {
            if ( is_a($element, DOMElement::class) ) {
                $elements = array_merge($elements, iterator_to_array($element->getElementsByTagName($tag)));
            }
        }
        return new Elements($elements, $this->_root_node, $this->_parent_document, $this->_options);
    }


    /**
     * Get an element at an index in the collection.
     *
     * @param   int         $index
     *
     * @return  Elements|null
     */
    public function element (int $index) : ?Elements
    {
        if ( $index < parent::count() && $index >= 0 ) {
            return new Elements(parent::offsetGet($index), $this->_root_node, $this->_parent_document, $this->_options);
        }
        return null;
    }


    /**
     * Return the current element collection as a simple array.
     *
     * @return  array
     */
    public function elements () : array
    {
        return parent::getArrayCopy();
    }


    /**
     * Append some new content to each element in the collection.
     *
     * @param   mixed       $value
     * @param   int         $options
     *
     * @return  Elements
     */
    public function append ($value, int $options = 0) : Elements
    {
        if ( is_a($value, Elements::class) ) {
            $new_elements = $value->elements();
            $i = count($new_elements);
            while ( $i-- ) {
                $new_elements[$i] = $this->_parent_document->importNode($new_elements[$i], true);
            }
        }
        else if ( is_string($value) ) {
            if ( $options & HTML::UNSAFE_HTML ) {
                //  Treat as html.
                $new_code = new DOMDocument();
                $new_code->loadHTML($value);
                if ( $new_code->documentElement === null ) {
                    throw new RuntimeException('Failed to load HTML content', EINVAL);
                }
                $new_elements = iterator_to_array($new_code->documentElement->firstChild->childNodes);
                $i = count($new_elements);
                while ( $i-- ) {
                    //  Each node (and its children) needs to be imported into
                    //  the current document before it can be cloned below.
                    $new_elements[$i] = $this->_parent_document->importNode($new_elements[$i], true);
                }
            }
            else {
                $new_elements = [$this->_parent_document->createTextNode($value)];
            }
        }
        else {
            throw new RuntimeException('Unhandled parameter type: ' . gettype($value), EINVAL);
        }
        foreach ($new_elements as $new_element) {
            $this->_for_all_do(function($element, $new_element){
                $element->appendChild($new_element->cloneNode(true));
            }, $new_element);
        }
        return $this;
    }


    /**
     * Set or return the content of each element in the collection.
     *
     * @return  mixed
     */
    public function value (?string $new_value = null)
    {
        if ( $new_value === null ) {
            $values = array_map(fn($element) => $element->nodeValue, parent::getArrayCopy());
            return count($values) > 1 ? $values : $values[0] ?? null;
        }
        return $this->_for_all_do(function($element, $value){$element->nodeValue = $value;}, $new_value);
    }


    /**
     * Set or return the content of each element in the collection.
     *
     * @return  mixed
     */
    public function content (...$arguments)
    {
        //  Alias for value().
        //  IMPORTANT NOTE: Setting the content here automatically converts
        //  the content using htmlspecialchars().
        if ( count($arguments) === 0 ) {
            $values = array_map(fn($element) => htmlspecialchars(trim($element->nodeValue)), parent::getArrayCopy());
            return count($values) > 1 ? $values : $values[0] ?? null;
        }
        return $this->_for_all_do(function($element, $value){$element->nodeValue = htmlspecialchars($value);}, func_get_arg(0));
    }


    /**
     * Delete every element from the current collection (and from their document).
     *
     * @return  Elements
     */
    public function delete (): Elements
    {
        $this->_for_all_do(function($element){
            $element->parentNode->removeChild($element);
        });
        $i = parent::count();
        while ( $i-- ) {
            parent::offsetUnset($i);
        }
        return $this;
    }


    /**
     * Return the ID attribute for each element in the collection.
     *
     * @return  null|array|string
     */
    public function id (): null|array|string
    {
        $values = array_map(fn($element) => $element->hasAttribute('id') ? $element->getAttribute('id') : '', parent::getArrayCopy());
        return count($values) > 1 ? $values : $values[0] ?? null;
    }


    /**
     * Return the tag name for each element in the collection.
     *
     * @return  null|array|string
     */
    public function tag (): null|array|string
    {
        $values = array_map(fn($element) => $element->nodeType == XML_ELEMENT_NODE ? strtolower($element->tagName) : '', parent::getArrayCopy());
        return count($values) > 1 ? $values : $values[0] ?? null;
    }


    /**
     * Return the text content for each element in the collection.
     *
     * @return  null|array|string
     */
    public function text (): null|array|string
    {
        $values = array_map(fn($element) => $element->textContent, parent::getArrayCopy());
        return count($values) > 1 ? $values : $values[0] ?? null;
    }


    /**
     * Return a new collection of the child elements of every element in the
     * current collection.
     *
     * @param   integer     $flags
     *
     * @return  Elements
     */
    public function children (int $flags = 0): Elements
    {
        //  Return a list of the children associated with this element.
        $children = array_map(function($element) use ($flags) {
            $child_nodes = [];
            $elements = iterator_to_array($element->childNodes);
            $i = count($elements);
            while ( $i-- ) {
                //  For now, only add DOMElement nodes to the heap.
                //  See also https://php.net/manual/en/class.domnode.php#domnode.props.nodetype
                if ( ($elements[$i]->nodeType == XML_ELEMENT_NODE) || (($flags & HTML::INCLUDE_TEXT_NODES) && $elements[$i]->nodeType == XML_TEXT_NODE) ) {
                    $child_nodes[] = $elements[$i];
                }
            }
            return array_reverse($child_nodes);
        }, parent::getArrayCopy());
        return new Elements(array_merge(...$children), $this->_root_node, $this->_parent_document, $this->_options);
    }


    /**
     * Return a collection of the immediate parent element for each element in
     * the current collection.
     *
     * @return  Elements
     */
    public function parent (): Elements
    {
        //  Return a list of the immediate parent element(s) for these elements.
        $parents = array_map(function($element){
            //  Walk up the tree until an XML_ELEMENT_NODE is found, or null.
            while ($element !== null && $element->parentNode !== null) {
                if ($element->parentNode->nodeType === XML_ELEMENT_NODE) {
                    return $element->parentNode;
                }
                $element = $element->parentNode;
            }
            return false;
        }, parent::getArrayCopy());
        return (new Elements(array_filter($parents), $this->_root_node, $this->_parent_document, $this->_options))->deduplicate();
    }


    /**
     * Return the value of an attribute for each element in the collection.
     *
     * @param   string      $attribute_name
     *
     * @return  mixed
     */
    public function get_attribute (string $attribute_name)
    {
        $values = array_map(fn($element) => ($attribute = $element->attributes->getNamedItem($attribute_name)) === null ? null : $attribute->value, parent::getArrayCopy());
        return count($values) > 1 ? $values : $values[0] ?? null;
    }


    /**
     * Set the value of an attribute for each element in the collection.
     *
     * @param   string      $attribute_name
     * @param   mixed       $new_value
     *
     * @return  Elements
     */
    public function set_attribute (string $attribute_name, $new_value): Elements
    {
        return $this->_for_all_do(function($element, $attribute_name, $value){
            $element->setAttribute($attribute_name, $value);
        }, $attribute_name, $new_value);
    }


    /**
     * Delete an attribute from each element in the collection.
     *
     * @param   string      $attribute_name
     *
     * @return  Elements
     */
    public function delete_attribute (string $attribute_name): Elements
    {
        return $this->_for_all_do(function($element, $attribute_name){
            $element->removeAttribute($attribute_name);
        }, $attribute_name);
    }


    /**
     * Set or return the "class" attribute for each element in the collection.
     *
     * @param   ?string     $classname
     *
     * @return  mixed
     */
    public function classname (?string $classname = null)
    {
        if ( $classname === null ) {
            $values = array_map(fn($element) => ($attribute = $element->attributes->getNamedItem('class')) === null ? '' : $attribute->value, parent::getArrayCopy());
            return count($values) > 1 ? $values : $values[0] ?? null;
        }
        return $this->_for_all_do(function($element, $classname){
            $element->setAttribute('class', $classname);
        }, $classname);
    }


    /**
     * Set or return the "class" attribute for each element in the collection,
     * parsed as an array of classes.
     *
     * @param   ?array     $classes
     *
     * @return  mixed
     */
    public function classnames (?array $classes = null)
    {
        //  Returns this element's class names as an array.
        if ( $classes === null ) {
            $values = array_map(fn($element) => ($attribute = $element->attributes->getNamedItem('class')) === null ? [] : explode(' ', $attribute->value), parent::getArrayCopy());
            return count($values) > 1 ? $values : $values[0] ?? null;
        }
        return $this->_for_all_do(function($element, $classes){
            $element->setAttribute('class', implode(' ', $classes));
        }, $classes);
    }


    /**
     * Add a class name to the "class" attribute for each element in the collection.
     *
     * @param   string|array  $class
     *
     * @return  Elements
     */
    public function add_class (string|array $class): Elements
    {
        if ( is_string($class) ) {
            $class = explode(' ', $class);
        }
        return $this->_for_all_do(function($element, $class){
            $classes = ($attribute = $element->attributes->getNamedItem('class')) === null ? '' : $attribute->value;
            $element->setAttribute('class', implode(' ', array_unique(array_merge(explode(' ', $classes), $class))));
        }, $class);
    }


    /**
     * Return only the unique members of the current set of elements.
     *
     * @return Elements
     */
    public function deduplicate (): Elements
    {
        $uniques = [];
        foreach (parent::getArrayCopy() as $element) {
            $n = count($uniques);
            while ($n--) {
                if ($element->isSameNode($uniques[$n])) {
                    continue 2;
                }
            }
            $uniques[] = $element;
        }
        return new Elements($uniques, $this->_root_node, $this->_parent_document, $this->_options);
    }


    /**
     * Return only the elements in the current set that match the CSS selector
     * component $selector.
     *
     * @param array $selector
     *
     * @return Elements
     */
    public function filter (array $selector): Elements
    {
        return new Elements(array_values(array_filter(parent::getArrayCopy(), function ($element) use ($selector) {
            if ( $selector['tag'] !== '' && strtolower($element->tagName) !== $selector['tag'] ) {
                return false;
            }
            if ( $selector['id'] !== '' && strtolower($element->getAttribute('id')) !== $selector['id'] ) {
                return false;
            }
            if ( $selector['class'] !== '' ) {
                //  Match elements with multiple classes.
                if ( ($classes = $element->attributes->getNamedItem('class')) === null ) {
                    return false;
                }
                $search_classes = array_unique(explode('.', $selector['class']));
                return count(array_intersect(array_unique(explode(' ', $classes->value)), $search_classes)) === count($search_classes);
            }
            foreach ($selector['attributes'] as $attribute_name => $search_value) {
                if ( ! $element->hasAttribute($attribute_name) ) {
                    return false;
                }
                if ( $search_value === '' ) {
                    return $element;
                }
                //  Attribute specifies a value which must be matched to the element's attribute.
                if ( $search_value[0] == '~' ) {
                    //  TODO
                }
                else if ( $search_value[0] == '|' ) {
                    //  TODO
                }
                else if ( $search_value != $element->getAttribute($attribute_name) ) {
                    //  Exact match required.
                    return false;
                }
            }
            return $element;
        })), $this->_root_node, $this->_parent_document, $this->_options);
    }


    /**
     * Return a subset of the current elements or their descendants matching a
     * CSS-style selector.
     *
     * @param   string      $selector
     *
     * @return  Elements
     */
    public function select (string $selector): Elements
    {
        $elements = $this->getArrayCopy();
        foreach (HTML::next_css_selector_component($selector) as $component) {
            $elements = $elements->getElementsByTagName($component['tag'] ?: '*')->filter($component);
        }
        return $elements->deduplicate();
    }

}
