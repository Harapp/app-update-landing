<?php

declare(strict_types=1);

namespace App\Domain;

final class LocaleResolver
{
    private const RIGHT_TO_LEFT_LANGUAGES = ['ar', 'he'];

    /** @param array<string, string> $translations */
    public function resolve(array $translations, string $locale): string
    {
        if (array_key_exists($locale, $translations)) {
            return $translations[$locale];
        }

        $language = strtolower(explode('-', $locale, 2)[0]);
        foreach ($translations as $translationLocale => $translation) {
            if (strtolower(explode('-', $translationLocale, 2)[0]) === $language) {
                return $translation;
            }
        }

        return $translations['en'];
    }

    public function textDirection(string $locale): string
    {
        return in_array($this->language($locale), self::RIGHT_TO_LEFT_LANGUAGES, true)
            ? 'rtl'
            : 'ltr';
    }

    public function language(string $locale): string
    {
        return strtolower(explode('-', $locale, 2)[0]);
    }
}
