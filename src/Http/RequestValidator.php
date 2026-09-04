<?php

declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

final class RequestValidator
{
    private const FORBIDDEN_PARAMETERS = [
        'deviceId',
        'device_id',
        'notificationToken',
        'notification_token',
        'token',
        'accessToken',
        'access_token',
        'authorization',
        'auth',
    ];

    /** @param array<string, mixed> $query */
    public function validate(array $query): UpdatePageRequest
    {
        foreach (self::FORBIDDEN_PARAMETERS as $parameter) {
            if (array_key_exists($parameter, $query)) {
                throw new InvalidArgumentException('Invalid request.');
            }
        }

        $appVersion = $this->optionalString($query, 'appVersion');
        $locale = $this->optionalString($query, 'locale');
        $platform = $this->optionalString($query, 'platform');
        $osVersion = $this->optionalString($query, 'osVersion');

        return new UpdatePageRequest(
            $appVersion === null ? null : $this->validateVersion($appVersion, false),
            $locale === null ? 'en' : $this->validateLocale($locale),
            $platform === null ? 'pc' : $this->validatePlatform($platform),
            $osVersion === null ? null : $this->validateVersion($osVersion, true),
        );
    }

    /** @param array<string, mixed> $query */
    private function optionalString(array $query, string $parameter): ?string
    {
        if (!array_key_exists($parameter, $query)) {
            return null;
        }
        if (!is_string($query[$parameter])) {
            throw new InvalidArgumentException('Invalid request.');
        }

        return $query[$parameter];
    }

    private function validateVersion(string $value, bool $allowSingleSegment): string
    {
        if (strlen($value) > 32) {
            throw new InvalidArgumentException('Invalid request.');
        }

        $pattern = $allowSingleSegment
            ? '/^\d{1,9}(?:\.\d{1,9}){0,3}$/D'
            : '/^\d{1,9}(?:\.\d{1,9}){1,3}$/D';

        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException('Invalid request.');
        }

        return $value;
    }

    private function validateLocale(string $value): string
    {
        if (strlen($value) > 35 || !preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/D', $value)) {
            throw new InvalidArgumentException('Invalid request.');
        }

        return $value;
    }

    private function validatePlatform(string $value): string
    {
        if (!in_array($value, ['ios', 'android', 'pc'], true)) {
            throw new InvalidArgumentException('Invalid request.');
        }

        return $value;
    }
}
