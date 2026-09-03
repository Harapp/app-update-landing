<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config\ConfigException;
use App\Config\ThemeRepository;
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
        $repository = new UpdatePageRepository($this->writeJson(['pages' => [$page]]), ['cdn.example.com', 'apps.apple.com']);

        $loaded = $repository->findByTargetVersion('2.0');

        self::assertNotNull($loaded);
        self::assertSame('event-update', $loaded['template']);
        self::assertSame('https://cdn.example.com/banner.webp', $loaded['imageUrl']);
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
        (new UpdatePageRepository($this->writeJson(['pages' => [$page]]), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testSchemaRejectsNonHttpsImageUrl(): void
    {
        $page = $this->basePage();
        $page['imageUrl'] = 'http://cdn.example.com/banner.webp';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson(['pages' => [$page]]), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testSchemaRejectsNonWebpImageUrl(): void
    {
        $page = $this->basePage();
        $page['imageUrl'] = 'https://cdn.example.com/banner.png';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson(['pages' => [$page]]), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testAdditionalValidationRejectsDuplicateVersions(): void
    {
        $first = $this->basePage();
        $second = $this->basePage();
        $second['targetVersion'] = '2.0';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson(['pages' => [$first, $second]]), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testAdditionalValidationRejectsInvalidPeriodOrder(): void
    {
        $page = $this->basePage();
        $page['startAt'] = '2026-09-04T00:00:00Z';
        $page['endAt'] = '2026-09-03T00:00:00Z';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson(['pages' => [$page]]), ['cdn.example.com']))->findByTargetVersion('2.0.0');
    }

    public function testAdditionalValidationRejectsDestinationHostOutsideAllowlist(): void
    {
        $page = $this->basePage();
        $page['destinationUrls']['ios'] = 'https://not-allowed.example/download';

        $this->expectException(ConfigException::class);
        (new UpdatePageRepository($this->writeJson(['pages' => [$page]]), ['cdn.example.com']))->findByTargetVersion('2.0.0');
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
            'primaryColor' => '#6D4C8D',
            'accentColor' => '#A63D72',
            'backgroundColor' => '#F7F1FA',
            'textColor' => '#2F2440',
        ]];
        yield 'red' => ['red', [
            'primaryColor' => '#A84448',
            'accentColor' => '#B24A50',
            'backgroundColor' => '#FFF3F3',
            'textColor' => '#3F2426',
        ]];
        yield 'blue' => ['blue', [
            'primaryColor' => '#3F6FA5',
            'accentColor' => '#3F6FA5',
            'backgroundColor' => '#F1F6FB',
            'textColor' => '#203044',
        ]];
        yield 'green' => ['green', [
            'primaryColor' => '#3F7D5A',
            'accentColor' => '#3F7D5A',
            'backgroundColor' => '#F1F8F3',
            'textColor' => '#20352A',
        ]];
        yield 'orange' => ['orange', [
            'primaryColor' => '#A4510C',
            'accentColor' => '#A4510C',
            'backgroundColor' => '#FFF7ED',
            'textColor' => '#3B2615',
        ]];
        yield 'pink' => ['pink', [
            'primaryColor' => '#A63D72',
            'accentColor' => '#A63D72',
            'backgroundColor' => '#FFF1F7',
            'textColor' => '#3F2230',
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

    public function testPurrfectSpiritsConfigurationPassesSchemaAndUsesFixedDestinations(): void
    {
        $root = dirname(__DIR__, 2);
        $hosts = ['neko.harapeco.okinawa', 'itunes.apple.com', 'play.google.com', 'www.harapeco.okinawa'];
        $page = (new UpdatePageRepository($root . '/games/purrfect-spirits/update-pages.json', $hosts))
            ->findByTargetVersion('0.1.0');
        $theme = (new ThemeRepository($root . '/games/purrfect-spirits/theme.json', $hosts))->load();

        self::assertNotNull($page);
        self::assertSame('https://neko.harapeco.okinawa/event-update/assets/banner.webp', $page['imageUrl']);
        self::assertSame('https://itunes.apple.com/jp/app/id1269423920', $page['destinationUrls']['ios']);
        self::assertSame('https://play.google.com/store/apps/details?id=okinawa.harapeco.catRestaurant', $page['destinationUrls']['android']);
        self::assertSame('https://www.harapeco.okinawa/info/app/neko_boku.html', $page['destinationUrls']['pc']);
        self::assertSame('#A84448', $theme['primaryColor']);
        self::assertSame('#B24A50', $theme['accentColor']);
        self::assertSame('#FFF3F3', $theme['backgroundColor']);
        self::assertSame('#3F2426', $theme['textColor']);
        self::assertSame('https://neko.harapeco.okinawa/event-update/assets/purrfect-spirits-logo.webp', $theme['logoUrl']);
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
            'minimumOsVersions' => ['ios' => '18.0'],
            'destinationUrls' => ['ios' => 'https://apps.apple.com/app/id1'],
            'descriptions' => ['en' => 'English'],
            'imageAltTexts' => ['en' => 'Banner'],
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
