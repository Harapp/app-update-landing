<?php

declare(strict_types=1);

namespace App\Http;

final class BrowserLocaleResolver
{
    public function resolve(mixed $requestedLocale, mixed $acceptLanguage): mixed
    {
        if ($requestedLocale !== null) {
            return $requestedLocale;
        }
        if (!is_string($acceptLanguage) || $acceptLanguage === '' || strlen($acceptLanguage) > 1024) {
            return 'en';
        }

        $locale = \Locale::acceptFromHttp($acceptLanguage);
        if (!is_string($locale) || $locale === '') {
            return 'en';
        }

        $normalized = str_replace('_', '-', $locale);
        if (strlen($normalized) > 35
            || !preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/D', $normalized)
        ) {
            return 'en';
        }

        return $normalized;
    }
}
