<?php

declare(strict_types=1);

namespace AppUpdateLanding\Development;

use App\Clock;
use App\Config\ThemeRepository;
use App\Config\UiTextRepository;
use App\Config\UpdatePageRepository;
use App\Domain\LocaleResolver;
use App\Domain\UpdatePageEvaluator;
use App\Http\RequestValidator;
use App\Presentation\UpdatePageViewModel;
use DateTimeImmutable;
use JsonException;
use RuntimeException;

final class PreviewScenarioFactory
{
    /** @var array<string, string> */
    public const SCENARIOS = [
        'available' => 'アップデート可能',
        'unreleased' => '未配信',
        'ended' => '期間終了',
        'up-to-date' => '更新済み',
        'disabled' => '無効',
        'unsupported-os' => 'OS非対応',
        'missing-destination' => '更新先なし',
        'unavailable' => 'ページ利用不可',
    ];

    /** @var array{key: string, updatePagesPath: string, themePath: string, uiTextsPath: string, allowedHosts: list<string>} */
    private array $gameConfig;

    /** @var array<string, mixed> */
    private array $document;

    /** @var array<string, mixed> */
    private array $basePage;

    /** @var array<string, array<string, string>> */
    private array $uiTexts;

    /** @var array{primaryColor: string, accentColor: string, backgroundColor: string, textColor: string, logoUrl: ?string, maxContentWidth: int} */
    private array $theme;

    public function __construct(private readonly string $projectRoot)
    {
        $gameConfig = require $projectRoot . '/config/game.php';
        if (!is_array($gameConfig)) {
            throw new RuntimeException('Game configuration is unavailable.');
        }
        /** @var array{key: string, updatePagesPath: string, themePath: string, uiTextsPath: string, allowedHosts: list<string>} $gameConfig */
        $this->gameConfig = $gameConfig;

        $repository = new UpdatePageRepository(
            $gameConfig['updatePagesPath'],
            $gameConfig['allowedHosts'],
        );
        $release = $repository->releaseConfig();
        $this->document = $this->readJsonObject($gameConfig['updatePagesPath']);
        $this->basePage = $this->findRawPage($release['releaseTargetVersion']);
        $this->uiTexts = (new UiTextRepository($gameConfig['uiTextsPath']))->load();
        $this->theme = (new ThemeRepository(
            $gameConfig['themePath'],
            $gameConfig['allowedHosts'],
        ))->load();
    }

    /** @return list<string> */
    public function locales(): array
    {
        $locales = [];
        foreach (['title', 'descriptions', 'imageAltTexts'] as $field) {
            $translations = $this->basePage[$field] ?? [];
            if (!is_array($translations)) {
                continue;
            }
            foreach (array_keys($translations) as $locale) {
                if (is_string($locale)) {
                    $locales[$locale] = true;
                }
            }
        }

        return array_keys($locales);
    }

    public function create(string $scenario, string $locale, string $platform): UpdatePageViewModel
    {
        if (!array_key_exists($scenario, self::SCENARIOS)) {
            throw new \InvalidArgumentException('Unknown preview scenario.');
        }
        if (!in_array($locale, $this->locales(), true)) {
            throw new \InvalidArgumentException('Unknown preview locale.');
        }
        if (!in_array($platform, ['ios', 'android', 'pc'], true)) {
            throw new \InvalidArgumentException('Unknown preview platform.');
        }

        if ($scenario === 'unavailable') {
            return UpdatePageViewModel::unavailable(
                $locale,
                (new LocaleResolver())->resolve($this->uiTexts['status.unavailable'], $locale),
            );
        }

        $page = $this->basePage;
        $page['enabled'] = true;
        $page['startAt'] = '2030-01-10T00:00:00Z';
        $page['endAt'] = '2030-01-20T00:00:00Z';
        $page['released'] = ['ios' => true, 'android' => true, 'pc' => true];
        $page['minimumOsVersions'] = ['ios' => '1', 'android' => '1', 'pc' => '1'];
        $page['destinationUrls'] = [
            'ios' => 'https://preview.invalid/update/ios',
            'android' => 'https://preview.invalid/update/android',
            'pc' => 'https://preview.invalid/update/pc',
        ];

        $clock = '2030-01-15T00:00:00Z';
        $request = ['locale' => $locale, 'platform' => $platform];
        switch ($scenario) {
            case 'unreleased':
                $page['released'][$platform] = false;
                break;
            case 'ended':
                $clock = '2030-01-21T00:00:00Z';
                break;
            case 'up-to-date':
                $request['appVersion'] = $page['targetVersion'];
                break;
            case 'disabled':
                $page['enabled'] = false;
                break;
            case 'unsupported-os':
                $page['minimumOsVersions'][$platform] = '99';
                $request['osVersion'] = '1';
                break;
            case 'missing-destination':
                unset($page['destinationUrls'][$platform]);
                break;
        }

        $previewDocument = $this->document;
        $previewDocument['pages'] = [$page];
        $temporaryPath = tempnam(sys_get_temp_dir(), 'event-update-preview-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create preview configuration.');
        }

        try {
            file_put_contents(
                $temporaryPath,
                json_encode($previewDocument, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
            $allowedHosts = [...$this->gameConfig['allowedHosts'], 'preview.invalid'];
            $repository = new UpdatePageRepository($temporaryPath, $allowedHosts);

            return (new UpdatePageEvaluator(
                $repository,
                new PreviewClock($clock),
                new LocaleResolver(),
                $this->theme,
                $this->uiTexts,
            ))->evaluate((new RequestValidator())->validate($request));
        } finally {
            unlink($temporaryPath);
        }
    }

    /** @return array<string, mixed> */
    private function readJsonObject(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Preview configuration is unavailable.');
        }
        try {
            $document = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Preview configuration is invalid.', 0, $exception);
        }
        if (!is_array($document) || array_is_list($document)) {
            throw new RuntimeException('Preview configuration is invalid.');
        }

        return $document;
    }

    /** @return array<string, mixed> */
    private function findRawPage(string $targetVersion): array
    {
        $pages = $this->document['pages'] ?? [];
        if (!is_array($pages)) {
            throw new RuntimeException('Preview page is unavailable.');
        }
        foreach ($pages as $page) {
            if (is_array($page) && ($page['targetVersion'] ?? null) === $targetVersion) {
                return $page;
            }
        }

        throw new RuntimeException('Preview page is unavailable.');
    }
}

final class PreviewClock implements Clock
{
    public function __construct(private readonly string $value)
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->value);
    }
}
