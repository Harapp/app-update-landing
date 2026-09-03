<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Clock;
use App\Config\UpdatePageRepository;
use App\Domain\LocaleResolver;
use App\Domain\UpdatePageEvaluator;
use App\Http\RequestValidator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class UpdatePageEvaluatorTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = tempnam(sys_get_temp_dir(), 'update-pages-');
        file_put_contents($this->configPath, json_encode([
            'pages' => [
                [
                    'targetVersion' => '2.0.0',
                    'enabled' => true,
                    'imageUrl' => 'https://cdn.example.com/banner.webp',
                    'startAt' => '2026-09-03T10:00:00Z',
                    'endAt' => '2026-09-04T10:00:00Z',
                    'minimumOsVersions' => ['ios' => '18.0'],
                    'destinationUrls' => ['ios' => 'https://apps.apple.com/app/id1'],
                    'descriptions' => ['en' => 'English', 'ja' => '日本語'],
                    'imageAltTexts' => ['en' => 'Banner', 'ja' => 'バナー'],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        unlink($this->configPath);
    }

    public function testEvaluationHonorsPriorityAndLocaleFallback(): void
    {
        $evaluator = new UpdatePageEvaluator(
            new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']),
            new FixedClock('2026-09-03T12:00:00Z'),
            new LocaleResolver(),
        );
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.10.0',
            'targetVersion' => '2.0',
            'locale' => 'ja-JP',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ]);

        $view = $evaluator->evaluate($request);

        self::assertSame('available', $view->state);
        self::assertSame('日本語', $view->description);
        self::assertTrue($view->showUpdate);
        self::assertTrue($view->showStoreNotice);
    }

    public function testDisabledUpdateTakesPriority(): void
    {
        $this->replacePage(['enabled' => false]);
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.0.0',
            'targetVersion' => '2.0.0',
            'locale' => 'en-US',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ]);

        $view = (new UpdatePageEvaluator(
            new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']),
            new FixedClock('2026-09-03T12:00:00Z'),
        ))->evaluate($request);

        self::assertSame('disabled', $view->state);
        self::assertFalse($view->showUpdate);
    }

    public function testMatchingTargetVersionIsReportedAsUpToDate(): void
    {
        $request = (new RequestValidator())->validate([
            'appVersion' => '2.0',
            'targetVersion' => '2.0.0',
            'locale' => 'en-US',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ]);

        $view = (new UpdatePageEvaluator(
            new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']),
            new FixedClock('2026-09-03T09:00:00Z'),
        ))->evaluate($request);

        self::assertSame('up-to-date', $view->state);
        self::assertFalse($view->showUpdate);
    }

    public function testUnknownTargetVersionMakesThePageUnavailable(): void
    {
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.0.0',
            'targetVersion' => '3.0.0',
            'locale' => 'en-US',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ]);

        $view = (new UpdatePageEvaluator(
            new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']),
            new FixedClock('2026-09-03T12:00:00Z'),
        ))->evaluate($request);

        self::assertSame('unavailable', $view->state);
        self::assertFalse($view->showUpdate);
    }

    public function testUpdateIsNotShownBeforeStartAndAtEndBoundary(): void
    {
        $repository = new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']);
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.0.0',
            'targetVersion' => '2.0.0',
            'locale' => 'fr-FR',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ]);

        $before = (new UpdatePageEvaluator($repository, new FixedClock('2026-09-03T09:59:59Z')))->evaluate($request);
        $atEnd = (new UpdatePageEvaluator($repository, new FixedClock('2026-09-04T10:00:00Z')))->evaluate($request);

        self::assertSame('not-started', $before->state);
        self::assertFalse($before->showUpdate);
        self::assertSame('ended', $atEnd->state);
    }

    public function testUnsupportedOsAndMissingDestinationAreSafeStates(): void
    {
        $repository = new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']);
        $validator = new RequestValidator();
        $base = [
            'appVersion' => '1.0.0',
            'targetVersion' => '2.0.0',
            'locale' => 'en-US',
            'platform' => 'ios',
            'osVersion' => '17.0',
        ];
        $unsupported = (new UpdatePageEvaluator($repository, new FixedClock('2026-09-03T12:00:00Z')))->evaluate($validator->validate($base));
        $missingDestination = (new UpdatePageEvaluator($repository, new FixedClock('2026-09-03T12:00:00Z')))->evaluate($validator->validate([...$base, 'platform' => 'android', 'osVersion' => '14']));

        self::assertSame('unsupported-os', $unsupported->state);
        self::assertSame('missing-destination', $missingDestination->state);
        self::assertFalse($missingDestination->showUpdate);
    }

    /** @param array<string, mixed> $changes */
    private function replacePage(array $changes): void
    {
        $config = json_decode((string) file_get_contents($this->configPath), true, 16, JSON_THROW_ON_ERROR);
        $config['pages'][0] = [...$config['pages'][0], ...$changes];
        file_put_contents($this->configPath, json_encode($config, JSON_THROW_ON_ERROR));
    }
}

final class FixedClock implements Clock
{
    public function __construct(private readonly string $value)
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->value, new DateTimeZone('UTC'));
    }
}
