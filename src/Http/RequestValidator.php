<?php

declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

final class RequestValidator
{
    private const REQUIRED_PARAMETERS = [
        'appVersion',
        'targetVersion',
        'locale',
        'platform',
        'osVersion',
    ];

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

        foreach (self::REQUIRED_PARAMETERS as $parameter) {
            if (!array_key_exists($parameter, $query) || !is_string($query[$parameter])) {
                throw new InvalidArgumentException('Invalid request.');
            }
        }

        return new UpdatePageRequest(
            $this->validateVersion($query['appVersion'], false),
            $this->validateVersion($query['targetVersion'], false),
            $this->validateLocale($query['locale']),
            $this->validatePlatform($query['platform']),
            $this->validateVersion($query['osVersion'], true),
        );
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
