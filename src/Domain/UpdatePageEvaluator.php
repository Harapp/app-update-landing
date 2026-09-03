<?php

declare(strict_types=1);

namespace App\Domain;

use App\Clock;
use App\Config\UpdatePageRepository;
use App\Http\UpdatePageRequest;
use App\Presentation\UpdatePageViewModel;

final class UpdatePageEvaluator
{
    public function __construct(
        private readonly UpdatePageRepository $repository,
        private readonly Clock $clock,
        private readonly LocaleResolver $localeResolver = new LocaleResolver(),
    ) {
    }

    public function evaluate(UpdatePageRequest $request): UpdatePageViewModel
    {
        $page = $this->repository->findByTargetVersion($request->targetVersion);
        if ($page === null) {
            return UpdatePageViewModel::unavailable();
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
        ];

        $appVersion = Version::fromString($request->appVersion);
        $targetVersion = Version::fromString($request->targetVersion);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        if (!$page['enabled']) {
            return $this->view($common, 'disabled', 'This update is currently unavailable.');
        }
        if ($appVersion->compare($targetVersion) >= 0) {
            return $this->view($common, 'up-to-date', "You're using the latest version.");
        }
        if ($page['startAt'] !== null && $now < $page['startAt']) {
            return $this->view($common, 'not-started', 'This update is not available yet.');
        }
        if ($page['endAt'] !== null && $now >= $page['endAt']) {
            return $this->view($common, 'ended', 'This update period has ended.');
        }

        $minimumOsVersion = $page['minimumOsVersions'][$request->platform] ?? null;
        if ($minimumOsVersion instanceof Version && Version::fromString($request->osVersion, true)->isLessThan($minimumOsVersion)) {
            return $this->view($common, 'unsupported-os', 'This update requires a newer OS version.', $request->osVersion, (string) $minimumOsVersion);
        }

        $destinationUrl = $page['destinationUrls'][$request->platform] ?? null;
        if ($destinationUrl === null) {
            return $this->view($common, 'missing-destination', 'This update is temporarily unavailable.');
        }

        return $this->view($common, 'available', 'A new version is available.', null, null, $destinationUrl, $request->platform !== 'pc');
    }

    /** @param array<string, mixed> $common */
    private function view(array $common, string $state, string $status, ?string $osVersion = null, ?string $minimumOsVersion = null, ?string $destinationUrl = null, bool $showStoreNotice = false): UpdatePageViewModel
    {
        return new UpdatePageViewModel(
            $state,
            $common['locale'],
            $common['imageUrl'],
            $common['imageAlt'],
            $common['description'],
            $status,
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
        );
    }
}
