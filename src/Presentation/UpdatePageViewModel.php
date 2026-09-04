<?php

declare(strict_types=1);

namespace App\Presentation;

use DateTimeImmutable;

final readonly class UpdatePageViewModel
{
    public ?string $eventPeriod;
    public string $updateButtonLabel;
    public string $updateButtonAriaLabel;
    public string $storeNotice;
    public ?string $osRequirementMessage;
    public string $comingSoonButtonLabel;
    public string $socialCardTitle;
    public string $socialCardDescription;
    public string $title;

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
        ?string $updateButtonLabel = null,
        ?string $updateButtonAriaLabel = null,
        ?string $storeNotice = null,
        ?string $osRequirementMessage = null,
        ?string $comingSoonButtonLabel = null,
        ?string $socialCardTitle = null,
        ?string $socialCardDescription = null,
        ?string $title = null,
    ) {
        $this->eventPeriod = $eventPeriod;
        $this->updateButtonLabel = $updateButtonLabel ?? 'Update and play the event';
        $this->updateButtonAriaLabel = $updateButtonAriaLabel
            ?? sprintf('Update to version %s and play the event', $targetVersion ?? '');
        $this->storeNotice = $storeNotice
            ?? 'Updates may take some time to appear on the App Store or Google Play. If the update is not available yet, please try again later.';
        $this->osRequirementMessage = $osRequirementMessage;
        $this->comingSoonButtonLabel = $comingSoonButtonLabel ?? 'Coming Soon';
        $this->socialCardTitle = $socialCardTitle ?? ($description !== '' ? $description : 'App update');
        $this->socialCardDescription = $socialCardDescription ?? $description;
        $this->title = $title ?? $description;
    }

    public static function unavailable(
        string $locale = 'en',
        string $statusMessage = 'This update page is currently unavailable.',
    ): self
    {
        return new self(
            'unavailable', $locale, null, null, '',
            $statusMessage,
            null, null, null, null, null, null, null, false, false, 'event-update', self::DEFAULT_THEME, ''
        );
    }
}
