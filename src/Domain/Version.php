<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final readonly class Version
{
    /** @var list<int> */
    private array $segments;

    private function __construct(array $segments)
    {
        $this->segments = $segments;
    }

    public static function fromString(string $value, bool $allowSingleSegment = false): self
    {
        $pattern = $allowSingleSegment
            ? '/^\d{1,9}(?:\.\d{1,9}){0,3}$/D'
            : '/^\d{1,9}(?:\.\d{1,9}){1,3}$/D';
        if (!preg_match($pattern, $value)) {
            throw new InvalidArgumentException('Invalid version.');
        }

        $segments = array_map(static fn (string $segment): int => (int) $segment, explode('.', $value));

        return new self($segments);
    }

    public function compare(self $other): int
    {
        $length = max(count($this->segments), count($other->segments));

        for ($index = 0; $index < $length; $index++) {
            $left = $this->segments[$index] ?? 0;
            $right = $other->segments[$index] ?? 0;

            if ($left !== $right) {
                return $left <=> $right;
            }
        }

        return 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function __toString(): string
    {
        return implode('.', $this->segments);
    }
}
