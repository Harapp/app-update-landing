<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Clock;
use App\Config\UpdatePageRepository;
use App\Domain\UpdatePageEvaluator;
use App\Http\RequestValidator;
use App\Presentation\HtmlRenderer;
use App\Presentation\TemplateRegistry;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventUpdateResponseTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = tempnam(sys_get_temp_dir(), 'event-update-integration-');
        self::assertNotFalse($this->configPath);
        $this->writePage($this->basePage());
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
    }

    #[DataProvider('stateCases')]
    /** @param array<string, string> $request @param array<string, mixed> $changes */
    public function testEachStateRendersTheExpectedMessageAndButton(
        string $clock,
        array $request,
        array $changes,
        string $expectedMessage,
        bool $hasUpdateButton,
        bool $updateButtonIsDisabled,
        bool $hasStoreNotice,
        bool $showsStatusMessage,
        ?string $expectedPeriod,
    ): void {
        if ($changes !== []) {
            $page = $this->basePage();
            foreach ($changes as $key => $value) {
                $page[$key] = $value;
            }
            $this->writePage($page);
        }

        $view = (new UpdatePageEvaluator(
            new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com', 'play.google.com', 'example.com']),
            new IntegrationClock($clock),
        ))->evaluate((new RequestValidator())->validate($request));
        $html = (new HtmlRenderer(new TemplateRegistry(dirname(__DIR__, 2) . '/templates')))->render($view);

        self::assertSame(
            $showsStatusMessage,
            str_contains($html, htmlspecialchars($expectedMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
        );
        self::assertSame($hasUpdateButton, strpos($html, '<div class="update-action">') !== false);
        self::assertSame($updateButtonIsDisabled, strpos($html, '<button class="update-link update-link--disabled"') !== false);
        if ($updateButtonIsDisabled) {
            self::assertStringNotContainsString('<a class="update-link"', $html);
        }
        self::assertSame($hasStoreNotice, strpos($html, 'Updates may take some time') !== false);
        self::assertStringNotContainsString('Event period:', $html);
        self::assertStringNotContainsString('Current: V', $html);
        if ($expectedPeriod === null) {
            self::assertStringNotContainsString('<p class="period">', $html);
        } else {
            self::assertStringContainsString('<p class="period">' . $expectedPeriod . '</p>', $html);
        }
    }

    /**
     * @return array<string, array{string, array<string, string>, array<string, mixed>, string, bool, bool, bool, bool, ?string}>
     */
    public static function stateCases(): array
    {
        $baseRequest = [
            'appVersion' => '1.0.0',
            'targetVersion' => '2.0.0',
            'locale' => 'en-US',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ];

        return [
            'available' => ['2026-09-03T12:00:00Z', $baseRequest, [], 'A new version is available.', true, false, true, false, 'Sep 3–4 (1 day remaining)'],
            'up-to-date' => ['2026-09-03T12:00:00Z', [...$baseRequest, 'appVersion' => '2.0.0'], [], "You're using the latest version.", false, false, false, true, 'Sep 3–4 (1 day remaining)'],
            'disabled' => ['2026-09-03T12:00:00Z', $baseRequest, ['enabled' => false], 'This update is currently unavailable.', false, false, false, true, 'Sep 3–4 (1 day remaining)'],
            'unreleased-before-event' => ['2026-09-03T12:00:00Z', $baseRequest, ['startAt' => '2026-09-03T13:00:00Z', 'released' => ['ios' => false, 'android' => true, 'pc' => true]], 'This update has not been released yet.', true, true, true, false, 'Sep 3–4 (starts in 1 day)'],
            'ended' => ['2026-09-03T12:00:00Z', $baseRequest, ['endAt' => '2026-09-03T11:00:00Z'], 'This update period has ended.', false, false, false, false, 'Ended.'],
            'unsupported-os' => ['2026-09-03T12:00:00Z', [...$baseRequest, 'osVersion' => '17.0'], [], 'This update requires a newer OS version.', false, false, false, true, 'Sep 3–4 (1 day remaining)'],
            'missing-destination' => ['2026-09-03T12:00:00Z', [...$baseRequest, 'platform' => 'android', 'osVersion' => '14'], ['destinationUrls' => ['ios' => 'https://apps.apple.com/app/id1']], 'This update is temporarily unavailable.', false, false, false, true, 'Sep 3–4 (1 day remaining)'],
            'unavailable' => ['2026-09-03T12:00:00Z', [...$baseRequest, 'targetVersion' => '3.0.0'], [], 'This update page is currently unavailable.', false, false, false, true, null],
        ];
    }

    /** @param array<string, mixed> $page */
    private function writePage(array $page): void
    {
        file_put_contents($this->configPath, json_encode([
            'publicBaseUrl' => 'https://cdn.example.com/event-update',
            'releaseTargetVersion' => '2.0.0',
            'pages' => [$page],
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function basePage(): array
    {
        return [
            'targetVersion' => '2.0.0',
            'enabled' => true,
            'imageUrl' => 'https://cdn.example.com/banner.webp',
            'startAt' => '2026-09-03T10:00:00Z',
            'endAt' => '2026-09-04T10:00:00Z',
            'released' => ['ios' => true, 'android' => true, 'pc' => true],
            'minimumOsVersions' => ['ios' => '18.0'],
            'destinationUrls' => ['ios' => 'https://apps.apple.com/app/id1'],
            'title' => ['en' => 'Event title'],
            'descriptions' => ['en' => 'English'],
            'socialCard' => [
                'title' => ['en' => 'Event update'],
                'description' => ['en' => 'Update the app and play the event.'],
            ],
            'imageAltTexts' => ['en' => 'Banner'],
        ];
    }
}

final class IntegrationClock implements Clock
{
    public function __construct(private readonly string $value)
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->value, new DateTimeZone('UTC'));
    }
}
