<?php

declare(strict_types=1);

namespace App\Config;

use JsonException;

final class UiTextRepository
{
    /** @var array<string, list<string>> */
    private const REQUIRED_PLACEHOLDERS = [
        'button.updateAriaLabel' => ['{version}'],
        'os.requirement' => ['{current}', '{required}'],
    ];

    public function __construct(
        private readonly string $path,
        ?string $schemaPath = null,
    ) {
        $this->schemaPath = $schemaPath ?? dirname(__DIR__, 2) . '/config/schema/ui-texts.schema.json';
    }

    private readonly string $schemaPath;

    /** @return array<string, array<string, string>> */
    public function load(): array
    {
        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            throw new ConfigException('UI text configuration is unavailable.');
        }

        try {
            $texts = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ConfigException('UI text configuration is invalid.', 0, $exception);
        }

        (new JsonSchemaValidator($this->schemaPath))->validate($texts);
        if (!is_array($texts) || array_is_list($texts)) {
            throw new ConfigException('UI text configuration is invalid.');
        }

        foreach ($texts as $key => $translations) {
            if (!is_string($key) || !is_array($translations) || array_is_list($translations)) {
                throw new ConfigException('UI text configuration is invalid.');
            }

            foreach ($translations as $translation) {
                if (!is_string($translation)) {
                    throw new ConfigException('UI text configuration is invalid.');
                }
                foreach (self::REQUIRED_PLACEHOLDERS[$key] ?? [] as $placeholder) {
                    if (!str_contains($translation, $placeholder)) {
                        throw new ConfigException('UI text configuration is invalid.');
                    }
                }
            }
        }

        /** @var array<string, array<string, string>> $texts */
        return $texts;
    }
}
