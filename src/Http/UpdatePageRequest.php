<?php

declare(strict_types=1);

namespace App\Http;

final readonly class UpdatePageRequest
{
    public function __construct(
        public string $appVersion,
        public string $targetVersion,
        public string $locale,
        public string $platform,
        public string $osVersion,
    ) {
    }
}
