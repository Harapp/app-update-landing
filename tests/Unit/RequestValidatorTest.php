<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\RequestValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RequestValidatorTest extends TestCase
{
    public function testRequiredParametersAreParsed(): void
    {
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.2',
            'targetVersion' => '2.0.0',
            'locale' => 'ja-JP',
            'platform' => 'ios',
            'osVersion' => '18.1',
        ]);

        self::assertSame('2.0.0', $request->targetVersion);
        self::assertSame('ios', $request->platform);
    }

    public function testMissingAndMalformedParametersAreRejected(): void
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
