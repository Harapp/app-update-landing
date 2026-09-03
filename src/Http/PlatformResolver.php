<?php

declare(strict_types=1);

namespace App\Http;

use Detection\MobileDetect;
use InvalidArgumentException;

final class PlatformResolver
{
    private const PLATFORMS = ['ios', 'android', 'pc'];

    public function resolve(mixed $requestedPlatform, mixed $userAgent): string
    {
        if ($requestedPlatform !== null && (!is_string($requestedPlatform) || !in_array($requestedPlatform, self::PLATFORMS, true))) {
            throw new InvalidArgumentException('Invalid request.');
        }

        if (is_string($userAgent) && $userAgent !== '' && strlen($userAgent) <= 1024) {
            $detect = new MobileDetect(null, ['autoInitOfHttpHeaders' => false]);
            $detect->setUserAgent($userAgent);

            if ($detect->isiOS()) {
                return 'ios';
            }
            if ($detect->isAndroidOS()) {
                return 'android';
            }
        }

        return $requestedPlatform ?? 'pc';
    }
}
