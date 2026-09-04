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
            'Sep 3–4 (1 day remaining)',
            '更新してイベントを遊ぶ',
            'バージョン2.0.0に更新してイベントを遊ぶ',
            socialCardTitle: 'Event update title',
            socialCardDescription: 'Event update card description',
            title: 'Event heading',
        );
        $html = (new HtmlRenderer(new TemplateRegistry(dirname(__DIR__, 2) . '/templates')))->render($view);

        self::assertStringNotContainsString('A new version is available.', $html);
        self::assertStringContainsString('<p class="period" dir="auto">Sep 3–4 (1 day remaining)</p>', $html);
        self::assertStringContainsString('<meta name="robots" content="noindex,nofollow,noarchive">', $html);
        self::assertStringNotContainsString('Current: V', $html);
        self::assertStringContainsString('<span><bdi dir="ltr">V2.0.0</bdi></span>', $html);
        self::assertStringContainsString('<small dir="auto">更新してイベントを遊ぶ</small>', $html);
        self::assertStringContainsString('aria-label="バージョン2.0.0に更新してイベントを遊ぶ"', $html);
        self::assertStringContainsString('<title>Event update title</title>', $html);
        self::assertStringContainsString('<meta name="description" content="Event update card description">', $html);
        self::assertStringContainsString('<meta property="og:title" content="Event update title">', $html);
        self::assertStringContainsString('<meta property="og:description" content="Event update card description">', $html);
        self::assertStringContainsString('<meta name="twitter:title" content="Event update title">', $html);
        self::assertStringContainsString('<meta name="twitter:description" content="Event update card description">', $html);
        self::assertStringContainsString('<h1 dir="auto">Event heading</h1>', $html);
        self::assertStringContainsString('<p class="description" dir="auto">Event description</p>', $html);
        self::assertStringNotContainsString('<meta property="og:image"', $html);
        self::assertLessThan(strpos($html, '<a class="update-link"'), strpos($html, '<p class="period"'));
    }

    public function testArabicPageUsesRightToLeftDirectionAndIsolatesTheVersion(): void
    {
        $view = new UpdatePageViewModel(
            'available', 'ar-SA', null, null, 'وصف الفعالية', 'يتوفر إصدار جديد.',
            '1.0.0', '2.9.0', null, null, null, null, 'https://example.com/download', true, true,
            'event-update', UpdatePageViewModel::DEFAULT_THEME,
            'من 5 سبتمبر إلى 4 أكتوبر (متبقي ٣ أيام)',
            'حدّث والعب الفعالية',
            'حدّث إلى الإصدار 2.9.0 والعب الفعالية',
            storeNotice: 'قد يستغرق ظهور التحديث بعض الوقت.',
            title: 'عنوان الفعالية',
        );
        $html = (new HtmlRenderer(new TemplateRegistry(dirname(__DIR__, 2) . '/templates')))->render($view);

        self::assertStringContainsString('<html lang="ar-SA" dir="rtl">', $html);
        self::assertStringContainsString('<h1 dir="auto">عنوان الفعالية</h1>', $html);
        self::assertStringContainsString('<span><bdi dir="ltr">V2.9.0</bdi></span>', $html);
        self::assertStringContainsString('<small dir="auto">حدّث والعب الفعالية</small>', $html);
    }
}
