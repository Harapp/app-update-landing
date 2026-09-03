<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Presentation\HtmlRenderer;
use App\Presentation\TemplateRegistry;
use App\Presentation\UpdatePageViewModel;
use PHPUnit\Framework\TestCase;

final class HtmlRendererTest extends TestCase
{
    public function testRenderedValuesAreEscapedAndUnavailablePageHasNoLink(): void
    {
        $view = new UpdatePageViewModel(
            'available', 'en', 'https://cdn.example.com/banner.webp', '<alt>', '<script>alert(1)</script>',
            'A new version is available.', '1.0.0', '2.0.0', null, null, null, null,
            'https://example.com/download?a=1&b=2', true, false, 'event-update'
        );
        $html = (new HtmlRenderer(new TemplateRegistry(dirname(__DIR__, 2) . '/templates')))->render($view);

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('a=1&amp;b=2', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('<meta property="og:image" content="https://cdn.example.com/banner.webp">', $html);
        self::assertStringContainsString('<meta name="twitter:image" content="https://cdn.example.com/banner.webp">', $html);
        self::assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        self::assertStringContainsString('<meta property="og:image:alt" content="&lt;alt&gt;">', $html);
    }

    public function testAvailablePageOmitsSupportingLinesAndUsesLocalizedCallToAction(): void
    {
        $view = new UpdatePageViewModel(
            'available', 'en', null, null, 'Event description', 'A new version is available.',
            '1.0.0', '2.0.0', null, null, null, null, 'https://example.com/download', true, false,
            'event-update', UpdatePageViewModel::DEFAULT_THEME,
            'Event period: 2026-09-03 10:00 UTC – 2026-09-04 10:00 UTC',
            '更新してイベントを遊ぶ',
            'バージョン2.0.0に更新してイベントを遊ぶ',
        );
        $html = (new HtmlRenderer(new TemplateRegistry(dirname(__DIR__, 2) . '/templates')))->render($view);

        self::assertStringNotContainsString('A new version is available.', $html);
        self::assertStringNotContainsString('Event period:', $html);
        self::assertStringNotContainsString('Current: V', $html);
        self::assertStringContainsString('<span>V2.0.0</span>', $html);
        self::assertStringContainsString('<small>更新してイベントを遊ぶ</small>', $html);
        self::assertStringContainsString('aria-label="バージョン2.0.0に更新してイベントを遊ぶ"', $html);
        self::assertStringContainsString('<title>Event description</title>', $html);
        self::assertStringNotContainsString('<meta property="og:image"', $html);
    }
}
