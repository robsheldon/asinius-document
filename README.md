# Asinius Document Library

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

This is the Document library for [Asinius](https://github.com/robsheldon/asinius-core). Use CSS selectors to parse and manipulate HTML, or parse markdown documents and convert them into HTML.


## Quickstart

```php
$html = new HTML('yourpage.html');
$html->select('#content section aside.note')->classname('info');
file_put_contents('newpage.html', $html->to_html());
```

## Table of contents

* [Quickstart](#quickstart)
* [Requirements](#requirements)
* [Class Reference](#class-reference)
  * [Document](#document)
  * [HTML](#html)
    * [HTML/Elements](#htmlelements)
    * [TODOs](#todos)
  * [Markdown](#markdown)
* [Testing](#testing)
* [License](#license)


## Requirements

Requires [ext-dom](https://www.php.net/manual/en/book.dom.php) and [ext-libxml](https://www.php.net/manual/en/book.libxml.php); both should usually be installed by default.

This library uses PHP 8+ language features, but was first built for PHP 5+. You should be able to transpile it to PHP 7 with [Rector](https://github.com/rectorphp/rector) or a similar tool.


## Class Reference

### Document

`Document` is an abstract base class that declares just a couple of methods:
* `__construct($data)` accepts several different types of data, including `Datastream\Resource` from [Asinius Core](https://github.com/robsheldon/asinius-core). 
* `mime_type()`should return a `string` specifying the MIME type of the Document
* `to_html()` will convert the internal document into html and return it as a `string`


### HTML

`Document\HTML` gives you tools for manipulating HTML documents with ease, using common CSS selectors, with a few additional helper functions. **Note:** current CSS selector support is a bit limited. It handles simple selectors just fine.
* `Document\HTML::next_css_selector_component()` is a `Generator` that parses CSS selectors into a series of simple arrays. Example:
    ```php
    foreach (HTML::next_css_selector_component($selector) as $component) {
        // Do something here.
    }
    ```
* `Document\HTML::parse_css_selector()` simply gives you all of the components parsed by `next_css_selector_component()`.
* `__construct()` loads some HTML content into the object. You can pass it a `DOMDocument` or a `string`, or anything that cna pretend to be one of those.
* `mime_type()` always returns `text/html`.
* `select()` accepts a CSS selector as a `string` and returns a `Document\HTML\Elements` object with the matching elements.
* `to_html()` returns the current Document object as an HTML string. If you have `ext-tidy` installed, you can pass `HTML::TIDY` in `$options` for some minimal cleanup of the output.


#### HTML/Elements

`Document\HTML\Elements` is an `ArrayIterator` that contains and manages a collection of HTML elements.

**NOTE:** Some of the functions below return _either_ an array or a scalar value (typically: `string`) depending on how many elements are in the collection. This is a convenience when you're certain you're working with a single element:
```php
$html->select('#thing')->content()...
```
...but it may be annoying when you're not certain whether the selector will match one element or multiple elements. I have found the convenience so far to outweigh the inconvenience.

These functions are `value()`, `content()`, `id()`, `tag()`, `text()`, `get_attribute()`, `classname()`, and `classnames()`

* `__construct()` is intended to be called by `Document\HTML` or by a few other functions in the `Elements` class.
* `__toString()` will return the current collection of elements as HTML. Handy for extracting data from pages.
* `getElementsByTagName()` returns a new `Elements` object containing descendant elements with a matching tag.
* `element()` returns the Nth element of the collection as a new `Elements` object.
* `elements()` meanwhile gives you a copy of all of the `DOMElement` objects in internal storage.
* `append()` adds some new content to the elements in the collection.
* `value()` sets or returns the raw "value" of each element in the collection (typically: their content).
* `content()` sets or returns the content of each element, similar to `value()`, but the content gets translated by `htmlspecialchars()` _in and out_ (NOT IDEMPOTENT!). Use this if you'd like to inject some user-submitted content into an element, or display an element's html on a page.
* `delete()` removes each element in the collection from the `DOMDocument` they belong to.
* `id()` returns the `id` attribute of each element (see **note** above).
* `tag()` returns their html tags.
* `text()` returns the `innerText` for each of the elements (including any descendant elements).
* `children()` returns a new `Elements` object containing each of the direct child nodes of each element in the collection.
* `parent()` likewise returns (and deduplicates) an `Elements` collection of the parent elements for each element.
* `get_attribute()` returns the value of a selected attribute for each element.
* `set_attribute()` sets the value of a selected attribute for each element.
* `delete_attribute()` deletes an attribute from each element.
* `classname()` sets or returns the classes of each element _as a string_.
* `classnames()` does the same thing as `classname()`, but _as an array_.
* `add_class()` adds a class to each element (will not add a class to elements that already have the given class).
* `deduplicate()` will remove duplcated elements from the current collection. This is mostly an internal call, but feel free to use it.
* `filter()` removes any elements in the current collection that don't match the CSS selector components. This is intended for internal use but there's no harm in making it public.
* `select()` applies a CSS selector (`string`) to the current collection of elements and returns a new `Elements` object containing the matches.


#### TODOs

* Add support for multiple classes in selectors (easy, just haven't gotten to it yet)
* Create CSSSelector and CSSSelectorComponent classes
* Improve support for attribute selectors (modifiers)
* `Elements->offsetSet()`
* Implement the interfaces implemented by `ArrayIterator` instead of extending it (reduce calls to `parent::getArrayCopy()`)


### Markdown

Workin' on it.


## Testing

A suite of `phpunit` tests can be found in the `test/unit` directory. `test/data` includes files needed for testing. I don't currently have a phpunit configuration file stored here, so for now, just invoke it with something like `phpunit --testdox test/unit`:
```
PHPUnit 10.3.4 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.1.12

......................                                            22 / 22 (100%)

Time: 00:00.029, Memory: 8.00 MB

HTML
 ✔ Generates HTML
 ✔ Has correct MIME type
 ✔ CSS parser
 ✔ Finds body element
 ✔ Finds paragraph element
 ✔ Does not find nonexistent elements
 ✔ Finds element by ID
 ✔ Finds element by classname
 ✔ Finds element by href
 ✔ Finds multiple elements by classname
 ✔ Finds nested elements
 ✔ Can get element values
 ✔ Finds child elements
 ✔ Finds parent elements
 ✔ Can get classnames as strings
 ✔ Can get classnames as arrays
 ✔ Can set element values
 ✔ Can set element classes
 ✔ Can set element classnames as array
 ✔ Can add class to element
 ✔ Can set element attribute
 ✔ Can delete element

OK (22 tests, 96 assertions)
```


## License

All of the Asinius project and its related modules are being released under the [MIT License](https://opensource.org/licenses/MIT). See [LICENSE](LICENSE).
