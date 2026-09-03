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
}
