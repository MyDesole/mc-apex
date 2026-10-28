<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Белый список HTML для новостей: разметка остаётся, опасное — нет.
 */
class HtmlSanitizerTest extends TestCase
{
    public function test_keeps_formatting_and_strips_danger(): void
    {
        $html = HtmlSanitizer::clean(
            '<h2 onclick="x()">Сезон 2</h2>'
            . '<p>Текст <strong>жирный</strong> и <a href="javascript:alert(1)">плохая</a>, '
            . '<a href="https://mc-apex.ru/shop" class="news-btn evil">в магазин</a></p>'
            . '<script>alert(1)</script><iframe src="https://x"></iframe>'
            . '<ul><li>раз</li><li>два</li></ul>'
            . '<img src="http://x.ru/a.png"><img src="https://x.ru/b.png" alt="Б" onerror="x()">'
            . '<div style="color:red">блок</div><span>спан</span>'
        );

        $this->assertStringContainsString('<h2>Сезон 2</h2>', $html);
        $this->assertStringContainsString('<strong>жирный</strong>', $html);
        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringContainsString('плохая', $html);
        $this->assertStringContainsString('href="https://mc-apex.ru/shop" target="_blank"', $html);
        $this->assertStringNotContainsString('news-btn evil', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('<ul><li>раз</li><li>два</li></ul>', $html);
        $this->assertStringNotContainsString('http://x.ru', $html);
        $this->assertStringContainsString('<img src="https://x.ru/b.png" alt="Б" loading="lazy">', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringContainsString('<p>блок</p>', $html);
        $this->assertStringContainsString('спан', $html);
    }

    public function test_button_link_class_is_kept(): void
    {
        $this->assertSame(
            '<p><a href="/shop" class="news-btn" target="_blank" rel="noopener noreferrer nofollow">Магазин</a></p>',
            HtmlSanitizer::clean('<p><a href="/shop" class="news-btn">Магазин</a></p>'),
        );
    }
}
