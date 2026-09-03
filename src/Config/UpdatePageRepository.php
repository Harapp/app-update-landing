<?php

declare(strict_types=1);

namespace App\Config;

use App\Domain\Version;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;

final class UpdatePageRepository
{
    /** @var array<string, true> */
    private array $allowedHosts;

    /** @param list<string> $allowedHosts */
    public function __construct(
        private readonly string $path,
        array $allowedHosts,
        ?string $schemaPath = null,
    )
    {
        $this->allowedHosts = array_fill_keys(array_map('strtolower', $allowedHosts), true);
        $this->schemaPath = $schemaPath ?? dirname(__DIR__, 2) . '/config/schema/update-pages.schema.json';
    }

    private readonly string $schemaPath;

    /** @return array<string, mixed>|null */
    public function findByTargetVersion(string $targetVersion): ?array
    {
        try {
            $requestedVersion = Version::fromString($targetVersion);
        } catch (\InvalidArgumentException $exception) {
            throw new ConfigException('Configuration lookup is invalid.', 0, $exception);
        }

        $document = $this->loadDocument();
        $publicBaseUrl = $this->normalizePublicBaseUrl($document['publicBaseUrl']);

        $found = null;
        $targetVersions = [];
        foreach ($document['pages'] as $page) {
            if (!is_array($page)) {
                throw new ConfigException('Configuration is invalid.');
            }

            $validated = $this->validatePage($page, $publicBaseUrl);
            $pageVersion = Version::fromString($validated['targetVersion']);
            foreach ($targetVersions as $knownVersion) {
                if ($pageVersion->compare($knownVersion) === 0) {
                    throw new ConfigException('Configuration is invalid.');
                }
            }
            $targetVersions[] = $pageVersion;

            if ($requestedVersion->compare($pageVersion) === 0) {
                if ($found !== null) {
                    throw new ConfigException('Configuration is invalid.');
                }

                $found = $validated;
            }
        }

        return $found;
    }

    /** @return array{publicBaseUrl: string, releaseTargetVersion: string} */
    public function releaseConfig(): array
    {
        $document = $this->loadDocument();
        $publicBaseUrl = $this->normalizePublicBaseUrl($document['publicBaseUrl']);
        $releaseTargetVersion = $document['releaseTargetVersion'];
        if (!is_string($releaseTargetVersion)
            || $this->findByTargetVersion($releaseTargetVersion) === null
        ) {
            throw new ConfigException('Configuration is invalid.');
        }

        return [
            'publicBaseUrl' => $publicBaseUrl,
            'releaseTargetVersion' => $releaseTargetVersion,
        ];
    }

    /** @param array<string, mixed> $page @return array<string, mixed> */
    private function validatePage(array $page, string $publicBaseUrl): array
    {
        $allowedFields = [
            'targetVersion', 'template', 'enabled', 'imageUrl', 'startAt', 'endAt',
            'released', 'minimumOsVersions', 'destinationUrls', 'descriptions', 'imageAltTexts',
        ];
        if (array_diff(array_keys($page), $allowedFields) !== []) {
            throw new ConfigException('Configuration is invalid.');
        }

        foreach (['targetVersion', 'enabled', 'imageUrl', 'released', 'descriptions', 'imageAltTexts'] as $required) {
            if (!array_key_exists($required, $page)) {
                throw new ConfigException('Configuration is invalid.');
            }
        }

        if (!is_string($page['targetVersion'])) {
            throw new ConfigException('Configuration is invalid.');
        }
        try {
            Version::fromString($page['targetVersion']);
        } catch (\InvalidArgumentException $exception) {
            throw new ConfigException('Configuration is invalid.', 0, $exception);
        }

        if (!is_bool($page['enabled']) || !is_string($page['imageUrl'])) {
            throw new ConfigException('Configuration is invalid.');
        }
        $imageUrl = $this->resolveImageUrl($page['imageUrl'], $publicBaseUrl);

        $template = $page['template'] ?? 'event-update';
        if (!is_string($template) || $template !== 'event-update') {
            throw new ConfigException('Configuration is invalid.');
        }

        foreach (['startAt', 'endAt'] as $dateField) {
            if (array_key_exists($dateField, $page) && $page[$dateField] !== null) {
                if (!is_string($page[$dateField]) || $this->parseDateTime($page[$dateField]) === null) {
                    throw new ConfigException('Configuration is invalid.');
                }
            }
        }

        $startAt = isset($page['startAt']) && is_string($page['startAt']) ? $this->parseDateTime($page['startAt']) : null;
        $endAt = isset($page['endAt']) && is_string($page['endAt']) ? $this->parseDateTime($page['endAt']) : null;
        if ($startAt !== null && $endAt !== null && $startAt >= $endAt) {
            throw new ConfigException('Configuration is invalid.');
        }

        $descriptions = $this->validateTranslations($page['descriptions'], 2000);
        $imageAltTexts = $this->validateTranslations($page['imageAltTexts'], 300);
        if (!isset($descriptions['en'], $imageAltTexts['en'])) {
            throw new ConfigException('Configuration is invalid.');
        }

        $minimumOsVersions = $this->validateVersionsByPlatform($page['minimumOsVersions'] ?? []);
        $destinationUrls = $this->validateUrlsByPlatform($page['destinationUrls'] ?? []);
        $released = $this->validateReleasedPlatforms($page['released']);

        return [
            'targetVersion' => $page['targetVersion'],
            'template' => $template,
            'enabled' => $page['enabled'],
            'imageUrl' => $imageUrl,
            'startAt' => $startAt,
            'endAt' => $endAt,
            'released' => $released,
            'minimumOsVersions' => $minimumOsVersions,
            'destinationUrls' => $destinationUrls,
            'descriptions' => $descriptions,
            'imageAltTexts' => $imageAltTexts,
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

    /** @return array{publicBaseUrl: string, releaseTargetVersion: string, pages: list<mixed>} */
    private function loadDocument(): array
    {
        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            throw new ConfigException('Configuration is unavailable.');
        }

        try {
            $document = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ConfigException('Configuration is invalid.', 0, $exception);
        }

        (new JsonSchemaValidator($this->schemaPath))->validate($document);

        if (!is_array($document)
            || array_diff(array_keys($document), ['publicBaseUrl', 'releaseTargetVersion', 'pages']) !== []
            || !isset($document['publicBaseUrl'], $document['releaseTargetVersion'], $document['pages'])
            || !is_string($document['publicBaseUrl'])
            || !is_string($document['releaseTargetVersion'])
            || !is_array($document['pages'])
            || !array_is_list($document['pages'])
        ) {
            throw new ConfigException('Configuration is invalid.');
        }

        return $document;
    }

    private function normalizePublicBaseUrl(string $value): string
    {
        $parts = parse_url($value);
        if (!$this->isAllowedHttpsUrl($value)
            || !is_array($parts)
            || isset($parts['query'], $parts['fragment'])
        ) {
            throw new ConfigException('Configuration is invalid.');
        }

        return rtrim($value, '/');
    }

    private function resolveImageUrl(string $value, string $publicBaseUrl): string
    {
        if ($this->isAllowedHttpsUrl($value)) {
            return $value;
        }

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._~-]*(?:\/[A-Za-z0-9][A-Za-z0-9._~-]*)*\.webp$/D', $value)
        ) {
            throw new ConfigException('Configuration is invalid.');
        }

        $resolved = $publicBaseUrl . '/' . $value;
        if (!$this->isAllowedHttpsUrl($resolved)) {
            throw new ConfigException('Configuration is invalid.');
        }

        return $resolved;
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            return null;
        }

        if (str_ends_with($value, 'Z')) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value, new DateTimeZone('UTC'));
            return $date !== false && $date->format('Y-m-d\\TH:i:s\\Z') === $value ? $date : null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:sP', $value);
        return $date !== false && $date->format('Y-m-d\\TH:i:sP') === $value ? $date : null;
    }

    /** @param mixed $translations @return array<string, string> */
    private function validateTranslations(mixed $translations, int $maxLength): array
    {
        if (!is_array($translations) || array_is_list($translations) || $translations === []) {
            throw new ConfigException('Configuration is invalid.');
        }

        $result = [];
        foreach ($translations as $locale => $text) {
            if (!is_string($locale) || !preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/D', $locale) || !is_string($text) || $text === '' || strlen($text) > $maxLength) {
                throw new ConfigException('Configuration is invalid.');
            }
            $result[$locale] = $text;
        }
        return $result;
    }

    /** @param mixed $versions @return array<string, Version> */
    private function validateVersionsByPlatform(mixed $versions): array
    {
        if ($versions === []) {
            return [];
        }
        if (!is_array($versions) || array_is_list($versions)) {
            throw new ConfigException('Configuration is invalid.');
        }
        $result = [];
        foreach ($versions as $platform => $version) {
            if (!in_array($platform, ['ios', 'android', 'pc'], true) || !is_string($version)) {
                throw new ConfigException('Configuration is invalid.');
            }
            try {
                $result[$platform] = Version::fromString($version, true);
            } catch (\InvalidArgumentException $exception) {
                throw new ConfigException('Configuration is invalid.', 0, $exception);
            }
        }
        return $result;
    }

    /** @param mixed $urls @return array<string, string> */
    private function validateUrlsByPlatform(mixed $urls): array
    {
        if ($urls === []) {
            return [];
        }
        if (!is_array($urls) || array_is_list($urls)) {
            throw new ConfigException('Configuration is invalid.');
        }
        $result = [];
        foreach ($urls as $platform => $url) {
            if (!in_array($platform, ['ios', 'android', 'pc'], true) || !is_string($url) || !$this->isAllowedHttpsUrl($url)) {
                throw new ConfigException('Configuration is invalid.');
            }
            $result[$platform] = $url;
        }
        return $result;
    }

    /** @param mixed $released @return array{ios: bool, android: bool} */
    private function validateReleasedPlatforms(mixed $released): array
    {
        if (
            !is_array($released)
            || array_is_list($released)
            || count($released) !== 2
            || !array_key_exists('ios', $released)
            || !array_key_exists('android', $released)
        ) {
            throw new ConfigException('Configuration is invalid.');
        }
        if (!is_bool($released['ios']) || !is_bool($released['android'])) {
            throw new ConfigException('Configuration is invalid.');
        }

        return ['ios' => $released['ios'], 'android' => $released['android']];
    }
}
