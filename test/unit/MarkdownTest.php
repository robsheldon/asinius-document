<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Asinius\Document\Markdown;

final class MarkdownTest extends TestCase
{

    private static string $_data_dir   = '';
    private static array  $_test_files = [
        '01_a_paragraph.md',
        '01_a_paragraph.html',
        '02_basic_inline_styles.md',
        '02_basic_inline_styles.html',
        '03_more_inline_styles.md',
        '03_more_inline_styles.html',
        '04_links.md',
        '04_links.html',
        '05_frontmatter.md',
        '05_frontmatter.html',
        '06_simple_document.md',
        '06_simple_document.html',
        '07_code_blocks.md',
        '07_code_blocks.html',
        '08_lists.md',
        '08_lists.html',
        '09_blockquote.md',
        '09_blockquote.html',
        '10_images.md',
        '10_images.html',
    ];
    private static array  $_test_data  = [];


    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        MarkdownTest::$_data_dir = implode(DIRECTORY_SEPARATOR, [dirname(__FILE__, 2), 'data', 'Markdown']);
        foreach (MarkdownTest::$_test_files as $data_file) {
            $file_path = implode(DIRECTORY_SEPARATOR, [MarkdownTest::$_data_dir, $data_file]);
            if ( ! file_exists($file_path) ) {
                throw new RuntimeException(sprintf('Required test data file "%s" not found at %s', $data_file, $file_path));
            }
            if ( ! is_readable($file_path) ) {
                throw new RuntimeException(sprintf('Required test data file "%s" exists but is not readable at %s', $data_file, $file_path));
            }
            self::$_test_data[$data_file] = file_get_contents($file_path);
        }
    }


    public function test_has_correct_MIME_type (): void
    {
        $this->assertSame('text/markdown', (new Markdown(self::$_test_data['01_a_paragraph.md']))->mime_type());
    }


    public function test_can_generate_a_paragraph (): void
    {
        $this->assertSame(self::$_test_data['01_a_paragraph.html'], (new Markdown(self::$_test_data['01_a_paragraph.md']))->to_html());
    }


    public function test_handles_simple_inline_styles (): void
    {
        $this->assertSame(self::$_test_data['02_basic_inline_styles.html'], (new Markdown(self::$_test_data['02_basic_inline_styles.md']))->to_html());
    }


    public function test_handles_more_complex_inline_styles (): void
    {
        $this->assertSame(self::$_test_data['03_more_inline_styles.html'], (new Markdown(self::$_test_data['03_more_inline_styles.md']))->to_html());
    }


    public function test_handles_links (): void
    {
        $this->assertSame(self::$_test_data['04_links.html'], (new Markdown(self::$_test_data['04_links.md']))->to_html());
    }


    public function test_parses_frontmatter (): void
    {
        $this->assertSame(self::$_test_data['05_frontmatter.html'], (new Markdown(self::$_test_data['05_frontmatter.md']))->to_html());
    }


    public function test_can_generate_a_simple_document (): void
    {
        $this->assertSame(self::$_test_data['06_simple_document.html'], (new Markdown(self::$_test_data['06_simple_document.md']))->to_html());
    }


    public function test_handles_code_blocks (): void
    {
        $this->assertSame(self::$_test_data['07_code_blocks.html'], (new Markdown(self::$_test_data['07_code_blocks.md']))->to_html());
    }


    public function test_handles_lists (): void
    {
        $this->assertSame(self::$_test_data['08_lists.html'], (new Markdown(self::$_test_data['08_lists.md']))->to_html());
    }


    public function test_blockquotes (): void
    {
        $this->assertSame(self::$_test_data['09_blockquote.html'], (new Markdown(self::$_test_data['09_blockquote.md']))->to_html());
    }

    public function test_images (): void
    {
        $this->assertSame(self::$_test_data['10_images.html'], (new Markdown(self::$_test_data['10_images.md']))->to_html());
    }

}
