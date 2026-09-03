<?php

declare(strict_types=1);

namespace App\Presentation;

use DateTimeImmutable;

final readonly class UpdatePageViewModel
{
    public function __construct(
        public string $state,
        public string $locale,
        public ?string $imageUrl,
        public ?string $imageAlt,
        public string $description,
        public string $statusMessage,
        public ?string $currentVersion,
        public ?string $targetVersion,
        public ?string $osVersion,
        public ?string $minimumOsVersion,
        public ?DateTimeImmutable $startAt,
        public ?DateTimeImmutable $endAt,
        public ?string $destinationUrl,
        public bool $showUpdate,
        public bool $showStoreNotice,
        public string $template,
    ) {
    }

    public static function unavailable(): self
    {
        return new self(
            'unavailable', 'en', null, null, '',
            'This update page is currently unavailable.',
            null, null, null, null, null, null, null, false, false, 'event-update'
        );
    }
}
