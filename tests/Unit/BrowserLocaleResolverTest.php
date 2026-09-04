<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\BrowserLocaleResolver;
use PHPUnit\Framework\TestCase;

final class BrowserLocaleResolverTest extends TestCase
{
    public function testRequestedLocaleTakesPriorityOverBrowserLanguage(): void
    {
        $resolver = new BrowserLocaleResolver();

        self::assertSame('en-US', $resolver->resolve('en-US', 'ja-JP,ja;q=0.9'));
    }

    public function testPreferredBrowserLanguageIsNormalized(): void
    {
        $resolver = new BrowserLocaleResolver();

        self::assertSame('ja-JP', $resolver->resolve(null, 'ja-JP,ja;q=0.9,en;q=0.8'));
        self::assertSame('zh-Hant-TW', $resolver->resolve(null, 'zh-Hant-TW,zh;q=0.9'));
    }

    public function testMissingOrInvalidBrowserLanguageFallsBackToEnglish(): void
    {
        $resolver = new BrowserLocaleResolver();

        self::assertSame('en', $resolver->resolve(null, null));
        self::assertSame('en', $resolver->resolve(null, ''));
        self::assertSame('en', $resolver->resolve(null, str_repeat('a', 1025)));
    }

    public function testMalformedRequestedLocaleIsLeftForRequestValidation(): void
    {
        $resolver = new BrowserLocaleResolver();
        $malformed = ['ja-JP'];

        self::assertSame($malformed, $resolver->resolve($malformed, 'en-US'));
    }
}
