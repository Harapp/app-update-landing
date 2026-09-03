<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config\ConfigException;
use App\Config\ThemeRepository;
use App\Config\UpdatePageRepository;
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
}
