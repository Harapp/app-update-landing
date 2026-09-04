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
    public function testOnlyPublicReleaseFieldsAreReturned(string $expectedPhase): void
    {
        $root = dirname(__DIR__, 2);
        $repository = new UpdatePageRepository(
            $root . '/games/purrfect-spirits/update-pages.json',
            ['neko.harapeco.okinawa', 'itunes.apple.com', 'play.google.com', 'www.harapeco.okinawa'],
        );
        $release = $repository->releaseConfig();
        $page = $repository->findByTargetVersion($release['releaseTargetVersion']);
        self::assertNotNull($page);
        self::assertInstanceOf(DateTimeImmutable::class, $page['startAt']);
        self::assertInstanceOf(DateTimeImmutable::class, $page['endAt']);

        $now = match ($expectedPhase) {
            'upcoming' => $page['startAt']->modify('-1 second'),
            'active' => $page['startAt'],
            'ended' => $page['endAt']->modify('+1 second'),
        };
        $clock = new class($now) implements Clock {
            public function __construct(private readonly DateTimeImmutable $now)
            {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };

        $status = (new PublicReleaseStatus($repository, $clock))->get();

        self::assertSame(['schemaVersion', 'releaseVersion', 'pageUrl', 'enabled', 'eventPeriod', 'platforms'], array_keys($status));
        self::assertSame(2, $status['schemaVersion']);
        self::assertSame('2.9.0', $status['releaseVersion']);
        self::assertSame('https://neko.harapeco.okinawa/event-update/', $status['pageUrl']);
        self::assertTrue($status['enabled']);
        self::assertSame([
            'startAt' => $page['startAt']->format('Y-m-d\\TH:i:sP'),
            'endAt' => $page['endAt']->format('Y-m-d\\TH:i:sP'),
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

    /** @return array<string, array{string}> */
    public static function eventPhases(): array
    {
        return [
            'upcoming' => ['upcoming'],
            'active' => ['active'],
            'ended' => ['ended'],
        ];
    }
}
