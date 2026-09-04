<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Clock;
use App\Config\UpdatePageRepository;
use App\Config\UiTextRepository;
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
            'publicBaseUrl' => 'https://cdn.example.com/event-update',
            'releaseTargetVersion' => '2.0.0',
            'pages' => [
                [
                    'targetVersion' => '2.0.0',
                    'enabled' => true,
                    'imageUrl' => 'https://cdn.example.com/banner.webp',
                    'startAt' => '2026-09-03T10:00:00Z',
                    'endAt' => '2026-09-04T10:00:00Z',
                    'released' => ['ios' => true, 'android' => true, 'pc' => true],
                    'minimumOsVersions' => ['ios' => '18.0'],
                    'destinationUrls' => ['ios' => 'https://apps.apple.com/app/id1'],
                    'title' => ['en' => 'Event title', 'ar' => 'عنوان الفعالية', 'he' => 'כותרת האירוע', 'ja' => 'イベントタイトル'],
                    'descriptions' => ['en' => 'English', 'ar' => 'الوصف', 'he' => 'תיאור', 'ja' => '日本語'],
                    'socialCard' => [
                        'title' => ['en' => 'Event update', 'ar' => 'تحديث الفعالية', 'he' => 'עדכון האירוע', 'ja' => 'イベントアップデート'],
                        'description' => ['en' => 'Update the app.', 'ar' => 'حدّث التطبيق.', 'he' => 'עדכנו את האפליקציה.', 'ja' => 'アプリを更新してください。'],
                    ],
                    'imageAltTexts' => ['en' => 'Banner', 'ar' => 'لافتة', 'he' => 'באנר', 'ja' => 'バナー'],
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
            null,
            (new UiTextRepository(dirname(__DIR__, 2) . '/templates/event-update/ui-texts.json'))->load(),
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
        self::assertSame('イベントタイトル', $view->title);
        self::assertSame('日本語', $view->description);
        self::assertSame('イベントアップデート', $view->socialCardTitle);
        self::assertSame('アプリを更新してください。', $view->socialCardDescription);
        self::assertTrue($view->showUpdate);
        self::assertTrue($view->showStoreNotice);
        self::assertSame('更新してイベントを遊ぶ', $view->updateButtonLabel);
        self::assertSame('バージョン2.0.0に更新してイベントを遊ぶ', $view->updateButtonAriaLabel);
        self::assertStringContainsString('時間がかかる場合があります', $view->storeNotice);
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

    public function testReleasedUpdateBeforeEventUsesPreparationButtonText(): void
    {
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.0.0',
            'locale' => 'ja-JP',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ]);

        $view = (new UpdatePageEvaluator(
            new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']),
            new FixedClock('2026-09-03T09:00:00Z'),
            uiTexts: (new UiTextRepository(dirname(__DIR__, 2) . '/templates/event-update/ui-texts.json'))->load(),
        ))->evaluate($request);

        self::assertSame('available', $view->state);
        self::assertSame('更新してイベントに備える', $view->updateButtonLabel);
        self::assertSame('バージョン2.0.0に更新してイベントに備える', $view->updateButtonAriaLabel);
    }

    public function testRequestedTargetVersionDoesNotOverrideReleaseTarget(): void
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

        self::assertSame('available', $view->state);
        self::assertSame('2.0.0', $view->targetVersion);
        self::assertTrue($view->showUpdate);
    }

    public function testReleaseFlagControlsUpdateBeforeEventStarts(): void
    {
        $this->replacePage(['released' => ['ios' => false, 'android' => true, 'pc' => true]]);
        $repository = new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']);
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.0.0',
            'targetVersion' => '2.0.0',
            'locale' => 'fr-FR',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ]);

        $unreleased = (new UpdatePageEvaluator($repository, new FixedClock('2026-09-03T09:59:59Z')))->evaluate($request);

        self::assertSame('unreleased', $unreleased->state);
        self::assertTrue($unreleased->showUpdate);
        self::assertTrue($unreleased->showStoreNotice);
        self::assertSame('https://apps.apple.com/app/id1', $unreleased->destinationUrl);
        self::assertSame('Event update', $unreleased->socialCardTitle);
        self::assertSame('Update the app.', $unreleased->socialCardDescription);
    }

    public function testUpdateEndsAfterTheEndBoundary(): void
    {
        $repository = new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']);
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.0.0',
            'targetVersion' => '2.0.0',
            'locale' => 'en',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ]);
        $atEnd = (new UpdatePageEvaluator($repository, new FixedClock('2026-09-04T10:00:00Z')))->evaluate($request);
        $afterEnd = (new UpdatePageEvaluator($repository, new FixedClock('2026-09-04T10:00:01Z')))->evaluate($request);

        self::assertSame('available', $atEnd->state);
        self::assertSame('ended', $afterEnd->state);
    }

    public function testUnreleasedPcShowsDisabledActionWithoutStoreNotice(): void
    {
        $this->replacePage([
            'released' => ['ios' => true, 'android' => true, 'pc' => false],
            'destinationUrls' => [
                'ios' => 'https://apps.apple.com/app/id1',
                'pc' => 'https://cdn.example.com/download',
            ],
        ]);
        $repository = new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']);
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.0.0',
            'targetVersion' => '2.0.0',
            'locale' => 'en',
            'platform' => 'pc',
            'osVersion' => '1',
        ]);

        $view = (new UpdatePageEvaluator($repository, new FixedClock('2026-09-03T12:00:00Z')))->evaluate($request);

        self::assertSame('unreleased', $view->state);
        self::assertTrue($view->showUpdate);
        self::assertFalse($view->showStoreNotice);
    }

    public function testJapaneseEventPeriodShowsCountdownRemainingDaysAndEndedState(): void
    {
        $this->replacePage([
            'startAt' => '2026-09-10T00:00:00+09:00',
            'endAt' => '2026-09-30T23:59:59+09:00',
            'released' => ['ios' => false, 'android' => true, 'pc' => true],
        ]);
        $repository = new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']);
        $texts = (new UiTextRepository(dirname(__DIR__, 2) . '/templates/event-update/ui-texts.json'))->load();
        $request = (new RequestValidator())->validate([
            'appVersion' => '1.0.0',
            'targetVersion' => '2.0.0',
            'locale' => 'ja-JP',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ]);

        $before = (new UpdatePageEvaluator($repository, new FixedClock('2026-09-03T00:00:00+09:00'), uiTexts: $texts))->evaluate($request);
        $active = (new UpdatePageEvaluator($repository, new FixedClock('2026-09-20T00:00:00+09:00'), uiTexts: $texts))->evaluate($request);
        $ended = (new UpdatePageEvaluator($repository, new FixedClock('2026-10-01T00:00:00+09:00'), uiTexts: $texts))->evaluate($request);

        self::assertSame('イベント期間：9月10日〜30日（7日後に開始）', $before->eventPeriod);
        self::assertSame('近日開始', $before->comingSoonButtonLabel);
        self::assertSame('イベント期間：9月10日〜30日（残り11日）', $active->eventPeriod);
        self::assertSame('イベント期間：終了しました。', $ended->eventPeriod);
    }

    public function testArabicAndHebrewEventPeriodsUseLocalizedDatesAndPluralForms(): void
    {
        $this->replacePage([
            'startAt' => '2026-09-10T00:00:00+09:00',
            'endAt' => '2026-09-30T23:59:59+09:00',
        ]);
        $repository = new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']);
        $texts = (new UiTextRepository(dirname(__DIR__, 2) . '/templates/event-update/ui-texts.json'))->load();
        $validator = new RequestValidator();
        $request = [
            'appVersion' => '1.0.0',
            'targetVersion' => '2.0.0',
            'platform' => 'ios',
            'osVersion' => '18.0',
        ];

        $arabicTwo = (new UpdatePageEvaluator(
            $repository,
            new FixedClock('2026-09-08T00:00:00+09:00'),
            uiTexts: $texts,
        ))->evaluate($validator->validate([...$request, 'locale' => 'ar']));
        $arabicFew = (new UpdatePageEvaluator(
            $repository,
            new FixedClock('2026-09-07T00:00:00+09:00'),
            uiTexts: $texts,
        ))->evaluate($validator->validate([...$request, 'locale' => 'ar']));
        $arabicMany = (new UpdatePageEvaluator(
            $repository,
            new FixedClock('2026-08-30T00:00:00+09:00'),
            uiTexts: $texts,
        ))->evaluate($validator->validate([...$request, 'locale' => 'ar']));
        $arabicRemaining = (new UpdatePageEvaluator(
            $repository,
            new FixedClock('2026-09-28T00:00:00+09:00'),
            uiTexts: $texts,
        ))->evaluate($validator->validate([...$request, 'locale' => 'ar']));
        $hebrewTwo = (new UpdatePageEvaluator(
            $repository,
            new FixedClock('2026-09-08T00:00:00+09:00'),
            uiTexts: $texts,
        ))->evaluate($validator->validate([...$request, 'locale' => 'he-IL']));

        self::assertSame('rtl', $arabicTwo->textDirection);
        self::assertSame('عنوان الفعالية', $arabicTwo->title);
        self::assertSame('فترة الفعالية: من 10 سبتمبر إلى 30 سبتمبر (يبدأ خلال يومين)', $arabicTwo->eventPeriod);
        self::assertSame('فترة الفعالية: من 10 سبتمبر إلى 30 سبتمبر (يبدأ خلال 3 أيام)', $arabicFew->eventPeriod);
        self::assertSame('فترة الفعالية: من 10 سبتمبر إلى 30 سبتمبر (يبدأ خلال 11 يومًا)', $arabicMany->eventPeriod);
        self::assertSame('فترة الفعالية: من 10 سبتمبر إلى 30 سبتمبر (متبقية 3 أيام)', $arabicRemaining->eventPeriod);
        self::assertSame('rtl', $hebrewTwo->textDirection);
        self::assertSame('כותרת האירוע', $hebrewTwo->title);
        self::assertSame('תקופת האירוע: מ־10 בספטמבר עד 30 בספטמבר (מתחיל בעוד יומיים)', $hebrewTwo->eventPeriod);
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

    public function testMissingVersionsSkipDependentChecks(): void
    {
        $repository = new UpdatePageRepository($this->configPath, ['cdn.example.com', 'apps.apple.com']);
        $request = (new RequestValidator())->validate([
            'locale' => 'en',
            'platform' => 'ios',
        ]);

        $view = (new UpdatePageEvaluator(
            $repository,
            new FixedClock('2026-09-03T12:00:00Z'),
        ))->evaluate($request);

        self::assertSame('available', $view->state);
        self::assertNull($view->currentVersion);
        self::assertSame('2.0.0', $view->targetVersion);
        self::assertNull($view->osVersion);
        self::assertSame('https://apps.apple.com/app/id1', $view->destinationUrl);
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
