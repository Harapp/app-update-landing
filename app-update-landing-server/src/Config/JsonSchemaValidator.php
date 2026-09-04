<?php

declare(strict_types=1);

namespace App\Config;

use JsonException;

/**
 * Validates the JSON Schema subset used by the checked-in application schemas.
 * The schemas remain portable JSON documents for use by deployment tooling.
 */
final class JsonSchemaValidator
{
    /** @var array<string, mixed> */
    private array $schema;

    public function __construct(string $schemaPath)
    {
        $contents = @file_get_contents($schemaPath);
        if ($contents === false) {
            throw new ConfigException('Configuration schema is unavailable.');
        }

        try {
            $schema = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ConfigException('Configuration schema is invalid.', 0, $exception);
        }

        if (!is_array($schema)) {
            throw new ConfigException('Configuration schema is invalid.');
        }

        $this->schema = $schema;
    }

    public function validate(mixed $value): void
    {
        $this->validateValue($value, $this->schema);
    }

    /** @param array<string, mixed> $schema */
    private function validateValue(mixed $value, array $schema): void
    {
        if (isset($schema['type']) && !$this->matchesType($value, $schema['type'])) {
            throw new ConfigException('Configuration is invalid.');
        }

        if (isset($schema['enum']) && (!is_array($schema['enum']) || !$this->containsStrict($schema['enum'], $value))) {
            throw new ConfigException('Configuration is invalid.');
        }

        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            throw new ConfigException('Configuration is invalid.');
        }

        if (is_string($value)) {
            $this->validateString($value, $schema);
        }

        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                throw new ConfigException('Configuration is invalid.');
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                throw new ConfigException('Configuration is invalid.');
            }
        }

        if (is_array($value) && array_is_list($value)) {
            if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
                throw new ConfigException('Configuration is invalid.');
            }
            if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
                throw new ConfigException('Configuration is invalid.');
            }
            if (isset($schema['items'])) {
                if (!is_array($schema['items'])) {
                    throw new ConfigException('Configuration schema is invalid.');
                }
                foreach ($value as $item) {
                    $this->validateValue($item, $schema['items']);
                }
            }
        }

        if (is_array($value) && (!array_is_list($value) || $value === [])) {
            $this->validateObject($value, $schema);
        }
    }

    /** @param array<string, mixed> $schema */
    private function validateString(string $value, array $schema): void
    {
        $length = strlen($value);
        if (isset($schema['minLength']) && $length < $schema['minLength']) {
            throw new ConfigException('Configuration is invalid.');
        }
        if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
            throw new ConfigException('Configuration is invalid.');
        }
        if (isset($schema['pattern']) && (!is_string($schema['pattern']) || preg_match('~' . $schema['pattern'] . '~D', $value) !== 1)) {
            throw new ConfigException('Configuration is invalid.');
        }
    }

    /** @param array<string, mixed> $value @param array<string, mixed> $schema */
    private function validateObject(array $value, array $schema): void
    {
        $required = $schema['required'] ?? [];
        if (!is_array($required)) {
            throw new ConfigException('Configuration schema is invalid.');
        }
        foreach ($required as $property) {
            if (!is_string($property) || !array_key_exists($property, $value)) {
                throw new ConfigException('Configuration is invalid.');
            }
        }

        $properties = $schema['properties'] ?? [];
        if (!is_array($properties)) {
            throw new ConfigException('Configuration schema is invalid.');
        }
        $patterns = $schema['patternProperties'] ?? [];
        if (!is_array($patterns)) {
            throw new ConfigException('Configuration schema is invalid.');
        }

        foreach ($value as $property => $propertyValue) {
            $matched = false;
            if (isset($properties[$property])) {
                if (!is_array($properties[$property])) {
                    throw new ConfigException('Configuration schema is invalid.');
                }
                $this->validateValue($propertyValue, $properties[$property]);
                $matched = true;
            }
            foreach ($patterns as $pattern => $propertySchema) {
                if (!is_string($pattern) || !is_array($propertySchema) || preg_match('~' . $pattern . '~D', $property) === false) {
                    throw new ConfigException('Configuration schema is invalid.');
                }
                if (preg_match('~' . $pattern . '~D', $property) === 1) {
                    $this->validateValue($propertyValue, $propertySchema);
                    $matched = true;
                }
            }
            if (!$matched && ($schema['additionalProperties'] ?? true) === false) {
                throw new ConfigException('Configuration is invalid.');
            }
        }
    }

    private function matchesType(mixed $value, mixed $type): bool
    {
        $types = is_array($type) ? $type : [$type];
        foreach ($types as $candidate) {
            $matches = match ($candidate) {
                'null' => $value === null,
                'boolean' => is_bool($value),
                // json_decode(..., true) represents both {} and [] as arrays.
                'object' => is_array($value) && (!array_is_list($value) || $value === []),
                'array' => is_array($value) && array_is_list($value),
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                default => throw new ConfigException('Configuration schema is invalid.'),
            };
            if ($matches) {
                return true;
            }
        }
        return false;
    }

    /** @param list<mixed> $values */
    private function containsStrict(array $values, mixed $expected): bool
    {
        foreach ($values as $value) {
            if ($value === $expected) {
                return true;
            }
        }
        return false;
    }
}
