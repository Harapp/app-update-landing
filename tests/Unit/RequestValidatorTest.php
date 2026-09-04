<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\RequestValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RequestValidatorTest extends TestCase
{
    public function testParametersAreParsed(): void
    {
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.2',
            'targetVersion' => '2.0.0',
            'locale' => 'ja-JP',
            'platform' => 'ios',
            'osVersion' => '18.1',
        ]);

        self::assertSame('1.2', $request->appVersion);
        self::assertSame('ios', $request->platform);
    }

    public function testMissingParametersUseSafeDefaults(): void
    {
        $request = (new RequestValidator())->validate([]);

        self::assertNull($request->appVersion);
        self::assertSame('en', $request->locale);
        self::assertSame('pc', $request->platform);
        self::assertNull($request->osVersion);
    }

    public function testMalformedParametersAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RequestValidator())->validate([
            'appVersion' => '1.2.3',
            'targetVersion' => '2.0.0',
            'locale' => 'en-US',
            'platform' => 'ios',
            'osVersion' => ['18.0'],
        ]);
    }

    public function testSensitiveParametersAreNotAccepted(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RequestValidator())->validate([
            'appVersion' => '1.2.3',
            'targetVersion' => '2.0.0',
            'locale' => 'en-US',
            'platform' => 'ios',
            'osVersion' => '18.0',
            'deviceId' => 'not-accepted',
        ]);
    }
}
