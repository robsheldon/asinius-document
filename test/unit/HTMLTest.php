<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Asinius\Document\HTML;

final class HTMLTest extends TestCase
{

    private static string $_data_dir   = '';
    private static array  $_test_files = [
        'basic.html',
        'basic_with_id.html',
        'basic_with_classname.html',
        'basic_with_href.html',
        'multi_by_classname.html',
        'nested.html',
        'the_kitchen_sink.html',
        'the_kitchen_sink_01.html',
        'the_kitchen_sink_02.html',
        'the_kitchen_sink_03.html',
        'the_kitchen_sink_04.html',
        'the_kitchen_sink_05.html',
        'the_kitchen_sink_06.html',
    ];
    private static array  $_test_data  = [];


    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        HTMLTest::$_data_dir = implode(DIRECTORY_SEPARATOR, [dirname(__FILE__, 2), 'data', 'HTML']);
        foreach (HTMLTest::$_test_files as $data_file) {
            $file_path = implode(DIRECTORY_SEPARATOR, [HTMLTest::$_data_dir, $data_file]);
            if ( ! file_exists($file_path) ) {
                throw new RuntimeException(sprintf('Required test data file "%s" not found at %s', $data_file, $file_path));
            }
            if ( ! is_readable($file_path) ) {
                throw new RuntimeException(sprintf('Required test data file "%s" exists but is not readable at %s', $data_file, $file_path));
            }
            self::$_test_data[$data_file] = file_get_contents($file_path);
        }
    }


    public function test_generates_HTML (): void
    {
        $this->assertXmlStringEqualsXmlString(self::$_test_data['basic.html'], (new HTML(self::$_test_data['basic.html']))->to_html());
    }


    public function test_has_correct_MIME_type (): void
    {
        $this->assertSame('text/html', (new HTML(self::$_test_data['basic.html']))->mime_type());
    }


    public function test_CSS_parser (): void
    {
        //  *tap* *tap* Is this thing on?
        $this->assertSame([['tag' => 'body', 'id' => '', 'class' => '', 'attributes' => []]], HTML::parse_css_selector('body'));
        $parts = HTML::parse_css_selector('body div');
        $this->assertCount(2, $parts);
        $this->assertSame(['tag' => 'body', 'id' => '', 'class' => '', 'attributes' => []], $parts[0]);
        $this->assertSame(['tag' => 'div', 'id' => '', 'class' => '', 'attributes' => []], $parts[1]);
        //  Classes.
        //  TODO: Need to add support for multiple classes. :-(
        $parts = HTML::parse_css_selector('body div.foo');
        $this->assertCount(2, $parts);
        $this->assertSame(['tag' => 'body', 'id' => '', 'class' => '', 'attributes' => []], $parts[0]);
        $this->assertSame(['tag' => 'div', 'id' => '', 'class' => 'foo', 'attributes' => []], $parts[1]);
        //  IDs.
        $parts = HTML::parse_css_selector('body #foo');
        $this->assertCount(2, $parts);
        $this->assertSame(['tag' => 'body', 'id' => '', 'class' => '', 'attributes' => []], $parts[0]);
        $this->assertSame(['tag' => '', 'id' => 'foo', 'class' => '', 'attributes' => []], $parts[1]);
        //  Attributes.
        $parts = HTML::parse_css_selector('body a[href]');
        $this->assertCount(2, $parts);
        $this->assertSame(['tag' => 'body', 'id' => '', 'class' => '', 'attributes' => []], $parts[0]);
        $this->assertSame(['tag' => 'a', 'id' => '', 'class' => '', 'attributes' => ['href' => '']], $parts[1]);
        $parts = HTML::parse_css_selector('body a[href="test.html"]');
        $this->assertCount(2, $parts);
        $this->assertSame(['tag' => 'body', 'id' => '', 'class' => '', 'attributes' => []], $parts[0]);
        $this->assertSame(['tag' => 'a', 'id' => '', 'class' => '', 'attributes' => ['href' => 'test.html']], $parts[1]);
    }


    public function test_finds_body_element (): void
    {
        $html = new HTML(self::$_test_data['basic.html']);
        $body_element = $html->select('body');
        $this->assertSame('body', $body_element->tag());
        $this->assertCount(1, $body_element);
    }


    public function test_finds_paragraph_element (): void
    {
        $html = new HTML(self::$_test_data['basic.html']);
        $p_element = $html->select('p');
        $this->assertSame('p', $p_element->tag());
        $this->assertCount(1, $p_element);
        $this->assertSame('Hello, world.', $p_element->text());
        $this->assertSame('Hello, world.', $p_element->content());
    }


    public function test_does_not_find_nonexistent_elements (): void
    {
        $html = new HTML(self::$_test_data['basic.html']);
        foreach (['div', '.none'] as $selector) {
            $nonexistent = $html->select($selector);
            $this->assertCount(0, $nonexistent);
            $this->assertSame(null, $nonexistent->tag());
            $this->assertSame(null, $nonexistent->content());
        }
    }


    public function test_finds_element_by_ID (): void
    {
        $html = new HTML(self::$_test_data['basic_with_id.html']);
        $div_element = $html->select('#foo');
        $this->assertSame('div', $div_element->tag());
        $this->assertCount(1, $div_element);
        $this->assertSame('foo', $div_element->id());
        $this->assertSame('Bar!', $div_element->content());
    }


    public function test_finds_element_by_classname (): void
    {
        $html = new HTML(self::$_test_data['basic_with_classname.html']);
        $div_element = $html->select('.foo');
        $this->assertSame('div', $div_element->tag());
        $this->assertCount(1, $div_element);
        $this->assertSame('foo', $div_element->classname());
        $this->assertSame('Baz!', $div_element->content());
    }


    public function test_finds_element_by_href (): void
    {
        $html = new HTML(self::$_test_data['basic_with_href.html']);
        $div_element = $html->select('a[href=/nav/page2.php]');
        $this->assertSame('a', $div_element->tag());
        $this->assertCount(1, $div_element);
        $this->assertSame('Next page', $div_element->content());
    }


    public function test_finds_multiple_elements_by_classname (): void
    {
        $html = new HTML(self::$_test_data['multi_by_classname.html']);
        $elements = $html->select('.zero');
        $this->assertCount(0, $elements);
        $elements = $html->select('div.none');
        $this->assertCount(0, $elements);
        $elements = $html->select('div.first');
        $this->assertCount(1, $elements);
        $this->assertSame('First!', $elements->content());
        $elements = $html->select('div.odd');
        $this->assertCount(2, $elements);
        $this->assertSame(['First!', 'Third!'], $elements->content());
        $this->assertSame(['div', 'div'], $elements->tag());
        $elements = $html->select('.even');
        $this->assertCount(2, $elements);
        $this->assertSame(['Second!', 'Fourth!'], $elements->content());
        $this->assertSame(['div', 'div'], $elements->tag());
    }


    public function test_finds_nested_elements (): void
    {
        $html = new HTML(self::$_test_data['nested.html']);
        $right_elements = $html->select('body div div.even .nested a');
        $this->assertCount(1, $right_elements);
        $this->assertSame('a', $right_elements->tag());
        $this->assertSame('right', $right_elements->get_attribute('href'));
        $this->assertSame('Baz', $right_elements->content());
        $wrong_elements = $html->select('body div div.odd .nested a');
        $this->assertCount(1, $wrong_elements);
        $this->assertSame('wrong', $wrong_elements->get_attribute('href'));
        $this->assertSame('Foo', $wrong_elements->content());
        $links = $html->select('div div a');
        $this->assertCount(2, $links);
    }


    public function test_can_get_element_values (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $nav_elements = $html->select('nav.main ul li');
        $this->assertCount(4, $nav_elements);
        $this->assertSame(['Home', 'About', 'FAQ', 'Contact'], $nav_elements->value());
    }


    public function test_finds_child_elements (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $li_elements = $html->select('body nav.main ul')->children();
        $this->assertCount(4, $li_elements);
        $this->assertSame(['Home', 'About', 'FAQ', 'Contact'], $li_elements->value());
    }


    public function test_finds_parent_elements (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $parents = $html->select('li')->parent();
        $this->assertCount(2, $parents);
        $this->assertSame(['ul', 'ul'], $parents->tag());
        $parent_nodes = $parents->elements();
        $this->assertFalse($parent_nodes[0]->isSameNode($parent_nodes[1]));
        $grandparents = $parents->parent();
        $this->assertCount(2, $grandparents);
        $this->assertSame(['nav', 'nav'], $grandparents->tag());
        $grandparent_nodes = $grandparents->elements();
        $this->assertFalse($grandparent_nodes[0]->isSameNode($grandparent_nodes[1]));
    }


    public function test_can_get_classnames_as_strings (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $nav_elements = $html->select('nav');
        $this->assertCount(2, $nav_elements);
        $this->assertSame(['main collapsed', 'sidenav'], $nav_elements->classname());
    }


    public function test_can_get_classnames_as_arrays (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $nav_elements = $html->select('nav');
        $this->assertCount(2, $nav_elements);
        $this->assertSame([['main', 'collapsed'], ['sidenav']], $nav_elements->classnames());
        $this->assertSame(['private', 'do-not-publish'], $html->select('a[href="/that-time-at-bandcamp"]')->parent()->classnames());
    }


    public function test_can_set_element_values (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $paragraphs = $html->select('section.main p');
        $this->assertCount(3, $paragraphs);
        $html->select('section.main p')->value('(sample paragraph text)');
        $this->assertSame(array_fill(0, 3, '(sample paragraph text)'), $html->select('section.main p')->value());
        $this->assertXmlStringEqualsXmlString(self::$_test_data['the_kitchen_sink_01.html'], $html->to_html());
    }


    public function test_can_set_element_classes (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $html->select('.private')->classname('hidden');
        $hidden = $html->select('.hidden');
        $this->assertCount(1, $hidden);
        $this->assertSame('li', $hidden->tag());
        $this->assertSame('hidden', $hidden->classname());
        $this->assertSame(['hidden'], $hidden->classnames());
        $this->assertXmlStringEqualsXmlString(self::$_test_data['the_kitchen_sink_02.html'], $html->to_html());
    }


    public function test_can_set_element_classnames_as_array (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $html->select('.private')->classnames(['hidden', 'unpublished', 'secret']);
        $hidden = $html->select('.hidden');
        $this->assertCount(1, $hidden);
        $this->assertSame('li', $hidden->tag());
        $this->assertSame('hidden unpublished secret', $hidden->classname());
        $this->assertSame(['hidden', 'unpublished', 'secret'], $hidden->classnames());
        $this->assertXmlStringEqualsXmlString(self::$_test_data['the_kitchen_sink_03.html'], $html->to_html());
    }


    public function test_can_add_class_to_element (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $html->select('.private')->add_class(['unpublished', 'secret']);
        $hidden = $html->select('.private');
        $this->assertCount(1, $hidden);
        $this->assertSame('li', $hidden->tag());
        $this->assertSame('private do-not-publish unpublished secret', $hidden->classname());
        $this->assertSame(['private', 'do-not-publish', 'unpublished', 'secret'], $hidden->classnames());
        $this->assertXmlStringEqualsXmlString(self::$_test_data['the_kitchen_sink_04.html'], $html->to_html());
    }


    public function test_can_set_element_attribute (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $html->select('.private a')->set_attribute('href', '/404');
        $redirected = $html->select('.private a');
        $this->assertSame('/404', $redirected->get_attribute('href'));
        $this->assertXmlStringEqualsXmlString(self::$_test_data['the_kitchen_sink_05.html'], $html->to_html());
    }


    public function test_can_delete_element (): void
    {
        $html = new HTML(self::$_test_data['the_kitchen_sink.html']);
        $html->select('.private')->delete();
        $hidden = $html->select('.private');
        $this->assertCount(0, $hidden);
        //$this->assertXmlStringEqualsXmlString(self::$_test_data['the_kitchen_sink_06.html'], $html->to_html());
        $this->assertSame(self::$_test_data['the_kitchen_sink_06.html'], $html->to_html());
    }

}
