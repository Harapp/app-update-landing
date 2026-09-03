<?php

declare(strict_types=1);

namespace App\Config;

use JsonException;

final class ThemeRepository
{
    /** @var array<string, array{primaryColor: string, accentColor: string, backgroundColor: string, textColor: string}> */
    private const COLOR_PRESETS = [
        'purple' => [
            'primaryColor' => '#6D4C8D',
            'accentColor' => '#A63D72',
            'backgroundColor' => '#F7F1FA',
            'textColor' => '#2F2440',
        ],
        'red' => [
            'primaryColor' => '#A84448',
            'accentColor' => '#B24A50',
            'backgroundColor' => '#FFF3F3',
            'textColor' => '#3F2426',
        ],
        'blue' => [
            'primaryColor' => '#3F6FA5',
            'accentColor' => '#3F6FA5',
            'backgroundColor' => '#F1F6FB',
            'textColor' => '#203044',
        ],
        'green' => [
            'primaryColor' => '#3F7D5A',
            'accentColor' => '#3F7D5A',
            'backgroundColor' => '#F1F8F3',
            'textColor' => '#20352A',
        ],
    ];

    /** @var array<string, true> */
    private array $allowedHosts;

    /** @param list<string> $allowedHosts */
    public function __construct(
        private readonly string $path,
        array $allowedHosts,
        ?string $schemaPath = null,
    ) {
        $this->allowedHosts = array_fill_keys(array_map('strtolower', $allowedHosts), true);
        $this->schemaPath = $schemaPath ?? dirname(__DIR__, 2) . '/config/schema/theme.schema.json';
    }

    private readonly string $schemaPath;

    /** @return array{primaryColor: string, accentColor: string, backgroundColor: string, textColor: string, logoUrl: ?string, maxContentWidth: int} */
    public function load(): array
    {
        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            throw new ConfigException('Theme is unavailable.');
        }

        try {
            $theme = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ConfigException('Theme is invalid.', 0, $exception);
        }

        (new JsonSchemaValidator($this->schemaPath))->validate($theme);
        if (!is_array($theme) || ($theme['logoUrl'] !== null && !$this->isAllowedHttpsUrl($theme['logoUrl']))) {
            throw new ConfigException('Theme is invalid.');
        }

        $colorKeys = ['primaryColor', 'accentColor', 'backgroundColor', 'textColor'];
        if (array_key_exists('colorPreset', $theme)) {
            foreach ($colorKeys as $colorKey) {
                if (array_key_exists($colorKey, $theme)) {
                    throw new ConfigException('Theme is invalid.');
                }
            }

            $colors = self::COLOR_PRESETS[$theme['colorPreset']] ?? null;
            if ($colors === null) {
                throw new ConfigException('Theme is invalid.');
            }
        } else {
            foreach ($colorKeys as $colorKey) {
                if (!array_key_exists($colorKey, $theme)) {
                    throw new ConfigException('Theme is invalid.');
                }
            }

            $colors = [
                'primaryColor' => $theme['primaryColor'],
                'accentColor' => $theme['accentColor'],
                'backgroundColor' => $theme['backgroundColor'],
                'textColor' => $theme['textColor'],
            ];
        }

        return [
            ...$colors,
            'logoUrl' => $theme['logoUrl'],
            'maxContentWidth' => $theme['maxContentWidth'],
        ];
    }

    private function isAllowedHttpsUrl(string $value): bool
    {
        $parts = parse_url($value);
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && isset($parts['host'])
            && !isset($parts['user'], $parts['pass'], $parts['port'])
            && isset($this->allowedHosts[strtolower($parts['host'])]);
    }
}
