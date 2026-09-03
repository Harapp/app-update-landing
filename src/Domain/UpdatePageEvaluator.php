<?php

declare(strict_types=1);

namespace App\Domain;

use App\Clock;
use App\Config\UpdatePageRepository;
use App\Http\UpdatePageRequest;
use App\Presentation\UpdatePageViewModel;

final class UpdatePageEvaluator
{
    /** @var array<string, array<string, string>> */
    private const DEFAULT_UI_TEXTS = [
        'status.available' => ['en' => 'A new version is available.'],
        'status.disabled' => ['en' => 'This update is currently unavailable.'],
        'status.upToDate' => ['en' => "You're using the latest version."],
        'status.notStarted' => ['en' => 'This update is not available yet.'],
        'status.ended' => ['en' => 'This update period has ended.'],
        'status.unsupportedOs' => ['en' => 'This update requires a newer OS version.'],
        'status.missingDestination' => ['en' => 'This update is temporarily unavailable.'],
        'status.unavailable' => ['en' => 'This update page is currently unavailable.'],
        'button.update' => ['en' => 'Update and play the event'],
        'button.updateAriaLabel' => ['en' => 'Update to version {version} and play the event'],
        'notice.storeDelay' => ['en' => 'Updates may take some time to appear on the App Store or Google Play. If the update is not available yet, please try again later.'],
        'os.requirement' => ['en' => 'OS: {current} · Required: {required}'],
    ];

    public function __construct(
        private readonly UpdatePageRepository $repository,
        private readonly Clock $clock,
        private readonly LocaleResolver $localeResolver = new LocaleResolver(),
        /** @var array{primaryColor: string, accentColor: string, backgroundColor: string, textColor: string, logoUrl: ?string, maxContentWidth: int}|null */
        private readonly ?array $theme = null,
        /** @var array<string, array<string, string>>|null */
        private readonly ?array $uiTexts = null,
    ) {
    }

    public function evaluate(UpdatePageRequest $request): UpdatePageViewModel
    {
        $page = $this->repository->findByTargetVersion($request->targetVersion);
        if ($page === null) {
            return UpdatePageViewModel::unavailable(
                $request->locale,
                $this->text('status.unavailable', $request->locale),
            );
        }

        $common = [
            'locale' => $request->locale,
            'imageUrl' => $page['imageUrl'],
            'imageAlt' => $this->localeResolver->resolve($page['imageAltTexts'], $request->locale),
            'description' => $this->localeResolver->resolve($page['descriptions'], $request->locale),
            'currentVersion' => $request->appVersion,
            'targetVersion' => $page['targetVersion'],
            'startAt' => $page['startAt'],
            'endAt' => $page['endAt'],
            'template' => $page['template'],
            'updateButtonLabel' => $this->text('button.update', $request->locale),
            'updateButtonAriaLabel' => $this->text(
                'button.updateAriaLabel',
                $request->locale,
                ['{version}' => $page['targetVersion']],
            ),
            'storeNotice' => $this->text('notice.storeDelay', $request->locale),
        ];

        $appVersion = Version::fromString($request->appVersion);
        $targetVersion = Version::fromString($request->targetVersion);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        if (!$page['enabled']) {
            return $this->view($common, 'disabled', 'status.disabled');
        }
        if ($appVersion->compare($targetVersion) >= 0) {
            return $this->view($common, 'up-to-date', 'status.upToDate');
        }
        if ($page['startAt'] !== null && $now < $page['startAt']) {
            return $this->view($common, 'not-started', 'status.notStarted');
        }
        if ($page['endAt'] !== null && $now >= $page['endAt']) {
            return $this->view($common, 'ended', 'status.ended');
        }

        $minimumOsVersion = $page['minimumOsVersions'][$request->platform] ?? null;
        if ($minimumOsVersion instanceof Version && Version::fromString($request->osVersion, true)->isLessThan($minimumOsVersion)) {
            return $this->view($common, 'unsupported-os', 'status.unsupportedOs', $request->osVersion, (string) $minimumOsVersion);
        }

        $destinationUrl = $page['destinationUrls'][$request->platform] ?? null;
        if ($destinationUrl === null) {
            return $this->view($common, 'missing-destination', 'status.missingDestination');
        }

        return $this->view($common, 'available', 'status.available', null, null, $destinationUrl, $request->platform !== 'pc');
    }

    /** @param array<string, mixed> $common */
    private function view(array $common, string $state, string $statusKey, ?string $osVersion = null, ?string $minimumOsVersion = null, ?string $destinationUrl = null, bool $showStoreNotice = false): UpdatePageViewModel
    {
        $osRequirementMessage = $osVersion !== null && $minimumOsVersion !== null
            ? $this->text('os.requirement', $common['locale'], [
                '{current}' => $osVersion,
                '{required}' => $minimumOsVersion,
            ])
            : null;

        return new UpdatePageViewModel(
            $state,
            $common['locale'],
            $common['imageUrl'],
            $common['imageAlt'],
            $common['description'],
            $this->text($statusKey, $common['locale']),
            $common['currentVersion'],
            $common['targetVersion'],
            $osVersion,
            $minimumOsVersion,
            $common['startAt'],
            $common['endAt'],
            $destinationUrl,
            $state === 'available',
            $showStoreNotice && $state === 'available',
            $common['template'],
            $this->theme ?? UpdatePageViewModel::DEFAULT_THEME,
            null,
            $common['updateButtonLabel'],
            $common['updateButtonAriaLabel'],
            $common['storeNotice'],
            $osRequirementMessage,
        );
    }

    /** @param array<string, string> $replacements */
    private function text(string $key, string $locale, array $replacements = []): string
    {
        $texts = $this->uiTexts ?? self::DEFAULT_UI_TEXTS;
        $translations = $texts[$key] ?? self::DEFAULT_UI_TEXTS[$key];
        return strtr($this->localeResolver->resolve($translations, $locale), $replacements);
    }
}
