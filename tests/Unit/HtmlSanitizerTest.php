<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * The filter between the panel's editor and the one place on the storefront
 * that prints markup unescaped.
 */
class HtmlSanitizerTest extends TestCase
{
    /* ------------------------------------------------------------- keeping */

    public function test_ordinary_prose_markup_survives_untouched(): void
    {
        $html = '<p>An <strong>important</strong> point and an <em>aside</em>.</p>'
              .'<h2>A heading</h2>'
              .'<ul><li>One</li><li>Two</li></ul>'
              .'<blockquote><p>Quoted.</p></blockquote>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    /**
     * The editor's block picker offers paragraph and h1 through h6, so all six
     * levels have to survive the filter - a heading that is silently unwrapped
     * would come back as a bare paragraph after saving.
     */
    public function test_every_heading_level_survives(): void
    {
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            $html = '<'.$tag.'>A heading</'.$tag.'>';

            $this->assertSame($html, HtmlSanitizer::clean($html), $tag.' should be kept');
        }
    }

    public function test_a_normal_link_keeps_its_href(): void
    {
        $out = HtmlSanitizer::clean('<p><a href="https://example.com">Read this</a></p>');

        $this->assertStringContainsString('href="https://example.com"', $out);
    }

    public function test_relative_and_mail_links_are_allowed(): void
    {
        foreach (['/shop', '#section', 'mailto:hello@example.com', 'tel:+14155550132'] as $href) {
            $out = HtmlSanitizer::clean('<p><a href="'.$href.'">Link</a></p>');

            $this->assertStringContainsString('href="'.$href.'"', $out, $href.' should be allowed');
        }
    }

    /* ------------------------------------------------------------ stripping */

    public function test_a_script_is_removed_with_its_contents(): void
    {
        $out = HtmlSanitizer::clean('<p>Before</p><script>alert(document.cookie)</script><p>After</p>');

        $this->assertStringNotContainsString('script', $out);
        $this->assertStringNotContainsString('alert', $out);
        $this->assertSame('<p>Before</p><p>After</p>', $out);
    }

    public function test_other_executable_elements_are_removed(): void
    {
        foreach (['<iframe src="https://evil.test"></iframe>', '<style>body{display:none}</style>',
            '<object data="x"></object>', '<embed src="x">', '<form><input name="pw"></form>'] as $markup) {
            $out = HtmlSanitizer::clean('<p>Text</p>'.$markup);

            $this->assertSame('<p>Text</p>', $out, $markup.' should be removed');
        }
    }

    public function test_event_handler_attributes_are_dropped(): void
    {
        $out = HtmlSanitizer::clean('<p onclick="steal()" onmouseover="steal()">Text</p>');

        $this->assertSame('<p>Text</p>', $out);
    }

    public function test_a_javascript_href_is_dropped(): void
    {
        $out = HtmlSanitizer::clean('<p><a href="javascript:alert(1)">Tap</a></p>');

        $this->assertStringNotContainsString('javascript', $out);
        $this->assertStringContainsString('Tap', $out);
    }

    public function test_a_data_uri_image_is_dropped(): void
    {
        $out = HtmlSanitizer::clean('<p><img src="data:text/html;base64,PHNjcmlwdD4="></p>');

        $this->assertStringNotContainsString('data:', $out);
    }

    /**
     * An unknown tag loses its markup but keeps its words - a paste from a
     * word processor should not silently delete a paragraph.
     */
    public function test_an_unknown_tag_is_unwrapped_rather_than_deleted(): void
    {
        $out = HtmlSanitizer::clean('<div class="wrapper"><p>Kept</p></div>');

        $this->assertSame('<p>Kept</p>', $out);
    }

    public function test_presentational_attributes_are_dropped(): void
    {
        $out = HtmlSanitizer::clean('<p class="ql-align-center" style="color:red" id="x">Text</p>');

        $this->assertSame('<p>Text</p>', $out);
    }

    public function test_comments_are_removed(): void
    {
        $out = HtmlSanitizer::clean('<p>Text</p><!--[if IE]><script>x()</script><![endif]-->');

        $this->assertSame('<p>Text</p>', $out);
    }

    /**
     * A link that opens a new tab hands the opener a window reference unless
     * it says otherwise, so the pairing is added rather than trusted.
     */
    public function test_a_new_tab_link_gets_its_rel(): void
    {
        $out = HtmlSanitizer::clean('<p><a href="https://example.com" target="_blank">Out</a></p>');

        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
    }

    /* ------------------------------------------------------------- helpers */

    public function test_utf8_characters_survive(): void
    {
        $out = HtmlSanitizer::clean('<p>Café — naïve · 20°C</p>');

        $this->assertStringContainsString('Café — naïve · 20°C', $out);
    }

    public function test_text_extraction_strips_markup(): void
    {
        $this->assertSame(
            'One Two',
            HtmlSanitizer::toText('<p>One</p><p>Two</p>'),
        );
    }

    /**
     * An untouched editor still posts a paragraph with a line break in it,
     * which is what "required" would otherwise accept as a post.
     */
    public function test_an_empty_editor_counts_as_blank(): void
    {
        $this->assertTrue(HtmlSanitizer::isBlank(''));
        $this->assertTrue(HtmlSanitizer::isBlank('<p><br></p>'));
        $this->assertTrue(HtmlSanitizer::isBlank('<p>   </p>'));
        $this->assertFalse(HtmlSanitizer::isBlank('<p>Something</p>'));
    }

    /**
     * A post that is only a picture is still a post.
     */
    public function test_an_image_alone_is_not_blank(): void
    {
        $this->assertFalse(HtmlSanitizer::isBlank('<p><img src="/uploads/blog/x.jpg"></p>'));
    }

    public function test_empty_input_returns_an_empty_string(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean('   '));
    }
}
