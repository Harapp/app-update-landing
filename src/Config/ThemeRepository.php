<?php

declare(strict_types=1);

namespace App\Config;

use JsonException;

final class ThemeRepository
{
    /** @var array<string, array{primaryColor: string, accentColor: string, backgroundColor: string, textColor: string}> */
    private const COLOR_PRESETS = [
        'purple' => [
            'primaryColor' => '#7C3AED',
            'accentColor' => '#8B2FD6',
            'backgroundColor' => '#F4EEFF',
            'textColor' => '#2D1746',
        ],
        'red' => [
            'primaryColor' => '#D92D3A',
            'accentColor' => '#C92F3C',
            'backgroundColor' => '#FFF0EA',
            'textColor' => '#3B1F22',
        ],
        'blue' => [
            'primaryColor' => '#1672D4',
            'accentColor' => '#0F64C5',
            'backgroundColor' => '#EDF7FF',
            'textColor' => '#172D46',
        ],
        'green' => [
            'primaryColor' => '#11854F',
            'accentColor' => '#087A45',
            'backgroundColor' => '#ECFAF2',
            'textColor' => '#153625',
        ],
        'orange' => [
            'primaryColor' => '#C64B08',
            'accentColor' => '#B94108',
            'backgroundColor' => '#FFF1DE',
            'textColor' => '#3B210D',
        ],
        'pink' => [
            'primaryColor' => '#CB2D70',
            'accentColor' => '#B92567',
            'backgroundColor' => '#FFF0F6',
            'textColor' => '#41192C',
        ],
        'gray' => [
            'primaryColor' => '#4B5563',
            'accentColor' => '#4B5563',
            'backgroundColor' => '#F3F4F6',
            'textColor' => '#1F2937',
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
