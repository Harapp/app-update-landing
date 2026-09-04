<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Version;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testVersionsAreComparedNumericallyAndMissingSegmentsAreZero(): void
    {
        self::assertTrue(Version::fromString('1.10.0')->compare(Version::fromString('1.2.0')) > 0);
        self::assertSame(0, Version::fromString('1.2')->compare(Version::fromString('1.2.0')));
    }

    public function testInvalidVersionsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Version::fromString('1.2-beta');
    }
}
