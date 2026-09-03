<?php

declare(strict_types=1);

namespace App\Domain;

final class LocaleResolver
{
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
}
