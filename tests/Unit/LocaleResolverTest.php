<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\LocaleResolver;
use PHPUnit\Framework\TestCase;

final class LocaleResolverTest extends TestCase
{
    public function testExactLocaleMatchTakesPriorityOverLanguageMatch(): void
    {
        $resolver = new LocaleResolver();

        self::assertSame('Canadian French', $resolver->resolve([
            'en' => 'English',
            'fr' => 'French',
            'fr-CA' => 'Canadian French',
        ], 'fr-CA'));
    }

    public function testLanguageMatchIsUsedWhenExactLocaleIsUnavailable(): void
    {
        $resolver = new LocaleResolver();

        self::assertSame('French', $resolver->resolve([
            'en' => 'English',
            'fr' => 'French',
        ], 'fr-CA'));
    }

    public function testEnglishIsUsedAsTheFinalFallback(): void
    {
        $resolver = new LocaleResolver();

        self::assertSame('English', $resolver->resolve([
            'en' => 'English',
            'ja' => '日本語',
        ], 'de-DE'));
    }

    public function testArabicAndHebrewUseRightToLeftDirection(): void
    {
        $resolver = new LocaleResolver();

        self::assertSame('rtl', $resolver->textDirection('ar-SA'));
        self::assertSame('rtl', $resolver->textDirection('he-IL'));
        self::assertSame('ltr', $resolver->textDirection('en-US'));
        self::assertSame('ltr', $resolver->textDirection('ja-JP'));
    }
}
