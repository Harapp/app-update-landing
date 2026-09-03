<?php

declare(strict_types=1);

namespace App\Presentation;

use DateTimeImmutable;

final readonly class UpdatePageViewModel
{
    public ?string $eventPeriod;

    /** @var array{primaryColor: string, accentColor: string, backgroundColor: string, textColor: string, logoUrl: ?string, maxContentWidth: int} */
    public const DEFAULT_THEME = [
        'primaryColor' => '#14532d',
        'accentColor' => '#2563eb',
        'backgroundColor' => '#f4f5f7',
        'textColor' => '#202124',
        'logoUrl' => null,
        'maxContentWidth' => 576,
    ];

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
        /** @var array{primaryColor: string, accentColor: string, backgroundColor: string, textColor: string, logoUrl: ?string, maxContentWidth: int} */
        public array $theme = self::DEFAULT_THEME,
        ?string $eventPeriod = null,
    ) {
        $this->eventPeriod = $eventPeriod ?? self::formatEventPeriod($startAt, $endAt);
    }

    public static function unavailable(): self
    {
        return new self(
            'unavailable', 'en', null, null, '',
            'This update page is currently unavailable.',
            null, null, null, null, null, null, null, false, false, 'event-update', self::DEFAULT_THEME, ''
        );
    }

    private static function formatEventPeriod(?DateTimeImmutable $startAt, ?DateTimeImmutable $endAt): string
    {
        $start = $startAt?->format('Y-m-d H:i \\U\\T\\C') ?? 'Immediately';
        $end = $endAt?->format('Y-m-d H:i \\U\\T\\C') ?? 'No end date';

        return sprintf('Event period: %s – %s', $start, $end);
    }
}
