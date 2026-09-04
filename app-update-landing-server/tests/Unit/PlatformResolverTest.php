<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\PlatformResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlatformResolverTest extends TestCase
{
    private const IOS_USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1';
    private const ANDROID_USER_AGENT = 'Mozilla/5.0 (Linux; Android 14; Pixel 8 Build/AP1A.240505.005) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Mobile Safari/537.36';
    private const DESKTOP_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/125.0 Safari/537.36';

    #[DataProvider('mobileCases')]
    public function testDetectedMobilePlatformOverridesSharedUrlParameter(string $requested, string $userAgent, string $expected): void
    {
        self::assertSame($expected, (new PlatformResolver())->resolve($requested, $userAgent));
    }

    /** @return array<string, array{string, string, string}> */
    public static function mobileCases(): array
    {
        return [
            'iOS overrides Android parameter' => ['android', self::IOS_USER_AGENT, 'ios'],
            'Android overrides iOS parameter' => ['ios', self::ANDROID_USER_AGENT, 'android'],
        ];
    }

    public function testRequestedPlatformIsFallbackForUnknownUserAgent(): void
    {
        self::assertSame('ios', (new PlatformResolver())->resolve('ios', self::DESKTOP_USER_AGENT));
    }

    public function testMissingPlatformFallsBackToPcWhenUserAgentIsUnknown(): void
    {
        self::assertSame('pc', (new PlatformResolver())->resolve(null, self::DESKTOP_USER_AGENT));
    }

    public function testInvalidRequestedPlatformIsRejectedBeforeDetection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PlatformResolver())->resolve('unknown', self::ANDROID_USER_AGENT);
    }
}
