<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config\ConfigException;
use App\Config\ThemeRepository;
use App\Config\UiTextRepository;
use App\Config\UpdatePageRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationRepositoryTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testUpdatePageSchemaAndRuntimeValuesAreLoaded(): void
    {
        $page = $this->basePage();
        $repository = new UpdatePageRepository($this->writeJson($this->document([$page])), ['cdn.example.com', 'apps.apple.com']);

        $loaded = $repository->findByTargetVersion('2.0');

        self::assertNotNull($loaded);
        self::assertSame('event-update', $loaded['template']);
        self::assertSame('https://cdn.example.com/banner.webp', $loaded['imageUrl']);
        self::assertSame('Event update', $loaded['socialCard']['title']['en']);
    }

    public function testRelativeImagePathIsResolvedAgainstPublicBaseUrl(): void
    {
        $page = $this->basePage();
        $page['imageUrl'] = 'assets/banner.webp';
        $repository = new UpdatePageRepository(
            $this->writeJson($this->document([$page])),
            ['cdn.example.com', 'apps.apple.com'],
        );

        $loaded = $repository->findByTargetVersion('2.0.0');

        self::assertNotNull($loaded);
        self::assertSame('https://cdn.example.com/event-update/assets/banner.webp', $loaded['imageUrl']);
    }

    public function testRelativeImagePathRequiresPublicBaseUrl(): void
    {
        $page = $this->basePage();
        $page['imageUrl'] = 'assets/banner.webp';
        $document = $this->document([$page]);
        unset($document['publicBaseUrl']);

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository(
            $this->writeJson($document),
            ['cdn.example.com'],
        ))->findByTargetVersion('2.0.0');
    }

    public function testSchemaRejectsRelativeImagePathTraversal(): void
    {
        $page = $this->basePage();
        $page['imageUrl'] = '../assets/banner.webp';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository(
            $this->writeJson($this->document([$page])),
            ['cdn.example.com'],
        ))->findByTargetVersion('2.0.0');
    }

    public function testReleaseTargetVersionMustReferenceAnExistingPage(): void
    {
        $document = $this->document([$this->basePage()]);
        $document['releaseTargetVersion'] = '3.0.0';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository(
            $this->writeJson($document),
            ['cdn.example.com', 'apps.apple.com'],
        ))->releaseConfig();
    }

    public function testMalformedJsonIsRejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'invalid-config-');
        self::assertNotFalse($path);
        $this->temporaryFiles[] = $path;
        file_put_contents($path, '{');

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($path, ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testSchemaRejectsUnknownFieldsAndMissingEnglishTranslations(): void
    {
        $page = $this->basePage();
        $page['unexpected'] = true;
        unset($page['descriptions']['en']);

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson($this->document([$page])), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testSocialCardRequiresEnglishTitleAndDescription(): void
    {
        $page = $this->basePage();
        unset($page['socialCard']['description']['en']);

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson($this->document([$page])), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testPageTitleRequiresEnglishAsTheFallback(): void
    {
        $page = $this->basePage();
        unset($page['title']['en']);

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson($this->document([$page])), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testSchemaRejectsNonHttpsImageUrl(): void
    {
        $page = $this->basePage();
        $page['imageUrl'] = 'http://cdn.example.com/banner.webp';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson($this->document([$page])), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testSchemaRejectsNonWebpImageUrl(): void
    {
        $page = $this->basePage();
        $page['imageUrl'] = 'https://cdn.example.com/banner.png';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson($this->document([$page])), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testAdditionalValidationRejectsDuplicateVersions(): void
    {
        $first = $this->basePage();
        $second = $this->basePage();
        $second['targetVersion'] = '2.0';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson($this->document([$first, $second])), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testAdditionalValidationRejectsInvalidPeriodOrder(): void
    {
        $page = $this->basePage();
        $page['startAt'] = '2026-09-04T00:00:00Z';
        $page['endAt'] = '2026-09-03T00:00:00Z';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson($this->document([$page])), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testReleasedRequiresBooleanFlagsForEveryPlatform(): void
    {
        $page = $this->basePage();
        $page['released'] = ['ios' => true];

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository(
            $this->writeJson($this->document([$page])),
            ['cdn.example.com', 'apps.apple.com'],
        ))->findByTargetVersion('2.0.0');
    }

    public function testAdditionalValidationRejectsDestinationHostOutsideAllowlist(): void
    {
        $page = $this->basePage();
        $page['destinationUrls']['ios'] = 'https://not-allowed.example/download';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson($this->document([$page])), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testThemeSchemaAndHostValidationLoadTheFixedGameTheme(): void
    {
        $theme = [
            'primaryColor' => '#123456',
            'accentColor' => '#abcdef',
            'backgroundColor' => '#f4f5f7',
            'textColor' => '#202124',
            'logoUrl' => 'https://cdn.example.com/logo.webp',
            'maxContentWidth' => 640,
        ];
        $repository = new ThemeRepository($this->writeJson($theme), ['cdn.example.com']);

        self::assertSame($theme, $repository->load());
    }

    #[DataProvider('colorPresetProvider')]
    public function testThemeColorPresetResolvesToACompletePalette(string $preset, array $expectedColors): void
    {
        $theme = [
            'colorPreset' => $preset,
            'logoUrl' => null,
            'maxContentWidth' => 640,
        ];

        self::assertSame(
            [...$expectedColors, 'logoUrl' => null, 'maxContentWidth' => 640],
            (new ThemeRepository($this->writeJson($theme), ['cdn.example.com']))->load(),
        );
    }

    /** @return iterable<string, array{string, array<string, string>}> */
    public static function colorPresetProvider(): iterable
    {
        yield 'purple' => ['purple', [
            'primaryColor' => '#7C3AED',
            'accentColor' => '#8B2FD6',
            'backgroundColor' => '#F4EEFF',
            'textColor' => '#2D1746',
        ]];
        yield 'red' => ['red', [
            'primaryColor' => '#D92D3A',
            'accentColor' => '#C92F3C',
            'backgroundColor' => '#FFF0EA',
            'textColor' => '#3B1F22',
        ]];
        yield 'blue' => ['blue', [
            'primaryColor' => '#1672D4',
            'accentColor' => '#0F64C5',
            'backgroundColor' => '#EDF7FF',
            'textColor' => '#172D46',
        ]];
        yield 'light-blue' => ['light-blue', [
            'primaryColor' => '#0E7490',
            'accentColor' => '#155E75',
            'backgroundColor' => '#ECFEFF',
            'textColor' => '#083344',
        ]];
        yield 'green' => ['green', [
            'primaryColor' => '#11854F',
            'accentColor' => '#087A45',
            'backgroundColor' => '#ECFAF2',
            'textColor' => '#153625',
        ]];
        yield 'orange' => ['orange', [
            'primaryColor' => '#C64B08',
            'accentColor' => '#B94108',
            'backgroundColor' => '#FFF1DE',
            'textColor' => '#3B210D',
        ]];
        yield 'pink' => ['pink', [
            'primaryColor' => '#CB2D70',
            'accentColor' => '#B92567',
            'backgroundColor' => '#FFF0F6',
            'textColor' => '#41192C',
        ]];
        yield 'gray' => ['gray', [
            'primaryColor' => '#4B5563',
            'accentColor' => '#4B5563',
            'backgroundColor' => '#F3F4F6',
            'textColor' => '#1F2937',
        ]];
    }

    public function testThemeRejectsUnknownPresetOrMixedPresetAndCustomColors(): void
    {
        $unknown = [
            'colorPreset' => 'teal',
            'logoUrl' => null,
            'maxContentWidth' => 640,
        ];

        try {
            (new ThemeRepository($this->writeJson($unknown), ['cdn.example.com']))->load();
            self::fail('Unknown preset should be rejected.');
        } catch (ConfigException) {
        }

        $mixed = [
            'colorPreset' => 'purple',
            'primaryColor' => '#123456',
            'accentColor' => '#abcdef',
            'backgroundColor' => '#f4f5f7',
            'textColor' => '#202124',
            'logoUrl' => null,
            'maxContentWidth' => 640,
        ];

        $this->expectException(ConfigException::class);
        (new ThemeRepository($this->writeJson($mixed), ['cdn.example.com']))->load();
    }

    #[DataProvider('colorPresetProvider')]
    public function testThemeColorPresetMaintainsReadableContrast(string $preset, array $colors): void
    {
        self::assertGreaterThanOrEqual(4.5, $this->contrastRatio($colors['primaryColor'], '#FFFFFF'), "$preset button contrast");
        self::assertGreaterThanOrEqual(4.5, $this->contrastRatio($colors['accentColor'], '#FFFFFF'), "$preset accent contrast");
        self::assertGreaterThanOrEqual(4.5, $this->contrastRatio($colors['textColor'], $colors['backgroundColor']), "$preset body contrast");
    }

    public function testThemeRejectsIncompleteCustomColors(): void
    {
        $theme = [
            'primaryColor' => '#123456',
            'logoUrl' => null,
            'maxContentWidth' => 640,
        ];

        $this->expectException(ConfigException::class);
        (new ThemeRepository($this->writeJson($theme), ['cdn.example.com']))->load();
    }

    public function testThemeRejectsInvalidColorAndUnknownField(): void
    {
        $theme = [
            'primaryColor' => 'red',
            'accentColor' => '#abcdef',
            'backgroundColor' => '#f4f5f7',
            'textColor' => '#202124',
            'logoUrl' => null,
            'maxContentWidth' => 640,
            'customCss' => 'body { color: red; }',
        ];

        $this->expectException(ConfigException::class);
        (new ThemeRepository($this->writeJson($theme), ['cdn.example.com']))->load();
    }

    public function testThemeSchemaRejectsNonWebpLogoUrl(): void
    {
        $theme = [
            'primaryColor' => '#123456',
            'accentColor' => '#abcdef',
            'backgroundColor' => '#f4f5f7',
            'textColor' => '#202124',
            'logoUrl' => 'https://cdn.example.com/logo.png',
            'maxContentWidth' => 640,
        ];

        $this->expectException(ConfigException::class);
        (new ThemeRepository($this->writeJson($theme), ['cdn.example.com']))->load();
    }

    public function testUiTextsRequireEnglishAsTheFallback(): void
    {
        $texts = $this->purrfectUiTexts();
        unset($texts['button.update']['en']);

        $this->expectException(ConfigException::class);
        (new UiTextRepository($this->writeJson($texts)))->load();
    }

    public function testUiTextsRequirePlaceholdersInEveryTranslation(): void
    {
        $texts = $this->purrfectUiTexts();
        $texts['button.updateAriaLabel']['ja'] = '更新してイベントを遊ぶ';

        $this->expectException(ConfigException::class);
        (new UiTextRepository($this->writeJson($texts)))->load();
    }

    public function testPurrfectSpiritsConfigurationPassesSchemaAndUsesFixedDestinations(): void
    {
        $root = dirname(__DIR__, 2);
        $hosts = ['neko.harapeco.okinawa', 'itunes.apple.com', 'play.google.com', 'www.harapeco.okinawa'];
        $repository = new UpdatePageRepository(
            $root . '/games/purrfect-spirits/update-pages.json',
            $hosts,
        );
        $release = $repository->releaseConfig();
        $page = $repository->findByTargetVersion($release['releaseTargetVersion']);
        $theme = (new ThemeRepository($root . '/games/purrfect-spirits/theme.json', $hosts))->load();
        $uiTexts = (new UiTextRepository($root . '/templates/event-update/ui-texts.json'))->load();

        self::assertNotNull($page);
        self::assertSame('https://neko.harapeco.okinawa/event-update', $release['publicBaseUrl']);
        self::assertSame('2.9.0', $release['releaseTargetVersion']);
        self::assertSame('https://neko.harapeco.okinawa/event-update/assets/banner.webp', $page['imageUrl']);
        self::assertSame('2026-09-05T00:00:00+09:00', $page['startAt']?->format('Y-m-d\\TH:i:sP'));
        self::assertSame('2026-10-04T23:59:59+09:00', $page['endAt']?->format('Y-m-d\\TH:i:sP'));
        self::assertTrue($page['released']['ios']);
        self::assertTrue($page['released']['android']);
        self::assertTrue($page['released']['pc']);
        self::assertSame('https://itunes.apple.com/jp/app/id1269423920', $page['destinationUrls']['ios']);
        self::assertSame('https://play.google.com/store/apps/details?id=okinawa.harapeco.catRestaurant', $page['destinationUrls']['android']);
        self::assertSame('https://www.harapeco.okinawa/info/app/neko_boku.html', $page['destinationUrls']['pc']);
        self::assertSame('Pampas grass, dumplings and a moonlit sky come to the room.', $page['title']['en']);
        self::assertArrayHasKey('ar', $page['title']);
        self::assertArrayHasKey('he', $page['title']);
        self::assertSame('すすきとだんごと月あかりの空が、お部屋にやってきます。', $page['title']['ja']);
        self::assertSame('Pampas grass, dumplings and a moonlit sky come to the room.', $page['socialCard']['title']['en']);
        self::assertSame('すすきとだんごと月あかりの空が、お部屋にやってきます。', $page['socialCard']['title']['ja']);
        self::assertSame('#0E7490', $theme['primaryColor']);
        self::assertSame('#155E75', $theme['accentColor']);
        self::assertSame('#ECFEFF', $theme['backgroundColor']);
        self::assertSame('#083344', $theme['textColor']);
        self::assertNull($theme['logoUrl']);
        self::assertSame('Update and play the event', $uiTexts['button.update']['en']);
        self::assertSame('حدّث والعب الفعالية', $uiTexts['button.update']['ar']);
        self::assertSame('עדכנו ושחקו באירוע', $uiTexts['button.update']['he']);
        self::assertSame('更新してイベントを遊ぶ', $uiTexts['button.update']['ja']);
        self::assertArrayHasKey('en', $uiTexts['notice.storeDelay']);
    }

    /** @return array<string, mixed> */
    private function basePage(): array
    {
        return [
            'targetVersion' => '2.0.0',
            'enabled' => true,
            'imageUrl' => 'https://cdn.example.com/banner.webp',
            'startAt' => null,
            'endAt' => null,
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

    /** @param list<array<string, mixed>> $pages @return array<string, mixed> */
    private function document(array $pages): array
    {
        return [
            'publicBaseUrl' => 'https://cdn.example.com/event-update',
            'releaseTargetVersion' => '2.0.0',
            'pages' => $pages,
        ];
    }

    /** @param array<string, mixed> $value */
    private function writeJson(array $value): string
    {
        $path = tempnam(sys_get_temp_dir(), 'configuration-');
        self::assertNotFalse($path);
        $this->temporaryFiles[] = $path;
        file_put_contents($path, json_encode($value, JSON_THROW_ON_ERROR));
        return $path;
    }

    /** @return array<string, array<string, string>> */
    private function purrfectUiTexts(): array
    {
        $path = dirname(__DIR__, 2) . '/templates/event-update/ui-texts.json';
        $texts = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        self::assertIsArray($texts);
        return $texts;
    }

    private function contrastRatio(string $first, string $second): float
    {
        $firstLuminance = $this->relativeLuminance($first);
        $secondLuminance = $this->relativeLuminance($second);

        return (max($firstLuminance, $secondLuminance) + 0.05) / (min($firstLuminance, $secondLuminance) + 0.05);
    }

    private function relativeLuminance(string $color): float
    {
        $channels = array_map(
            static function (string $hex): float {
                $channel = hexdec($hex) / 255;
                return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($color, 1), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
