<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Clock;
use App\Config\UpdatePageRepository;
use App\Domain\PublicReleaseStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicReleaseStatusTest extends TestCase
{
    #[DataProvider('eventPhases')]
    public function testOnlyPublicReleaseFieldsAreReturned(string $now, string $expectedPhase): void
    {
        $root = dirname(__DIR__, 2);
        $repository = new UpdatePageRepository(
            $root . '/games/purrfect-spirits/update-pages.json',
            ['neko.harapeco.okinawa', 'itunes.apple.com', 'play.google.com', 'www.harapeco.okinawa'],
        );
        $clock = new class($now) implements Clock {
            public function __construct(private readonly string $now)
            {
            }

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->now);
            }
        };

        $status = (new PublicReleaseStatus($repository, $clock))->get();

        self::assertSame(['schemaVersion', 'releaseVersion', 'pageUrl', 'enabled', 'eventPeriod', 'platforms'], array_keys($status));
        self::assertSame(2, $status['schemaVersion']);
        self::assertSame('2.9.0', $status['releaseVersion']);
        self::assertSame('https://neko.harapeco.okinawa/event-update/', $status['pageUrl']);
        self::assertTrue($status['enabled']);
        self::assertSame([
            'startAt' => '2026-09-05T00:00:00+09:00',
            'endAt' => '2026-10-04T23:59:59+09:00',
            'phase' => $expectedPhase,
        ], $status['eventPeriod']);
        self::assertSame([
            'released' => true,
        ], $status['platforms']['ios']);
        self::assertSame([
            'released' => true,
        ], $status['platforms']['android']);
        self::assertSame([
            'released' => true,
        ], $status['platforms']['pc']);
    }

    /** @return array<string, array{string, string}> */
    public static function eventPhases(): array
    {
        return [
            'upcoming' => ['2026-09-04T00:00:00+09:00', 'upcoming'],
            'active' => ['2026-09-05T00:00:00+09:00', 'active'],
            'ended' => ['2026-10-05T00:00:00+09:00', 'ended'],
        ];
    }
}
