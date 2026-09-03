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
    }

    public function testEventPeriodIsRenderedBetweenStatusAndVersion(): void
    {
        $view = new UpdatePageViewModel(
            'available', 'en', null, null, 'Event description', 'A new version is available.',
            '1.0.0', '2.0.0', null, null, null, null, 'https://example.com/download', true, false,
            'event-update', UpdatePageViewModel::DEFAULT_THEME,
            'Event period: 2026-09-03 10:00 UTC – 2026-09-04 10:00 UTC'
        );
        $html = (new HtmlRenderer(new TemplateRegistry(dirname(__DIR__, 2) . '/templates')))->render($view);

        $statusPosition = strpos($html, 'A new version is available.');
        $periodPosition = strpos($html, 'Event period: 2026-09-03 10:00 UTC – 2026-09-04 10:00 UTC');
        $versionPosition = strpos($html, 'Current: V1.0.0');

        self::assertIsInt($statusPosition);
        self::assertIsInt($periodPosition);
        self::assertIsInt($versionPosition);
        self::assertLessThan($periodPosition, $statusPosition);
        self::assertLessThan($versionPosition, $periodPosition);
    }
}
