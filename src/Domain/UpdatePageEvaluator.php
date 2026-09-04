<?php

declare(strict_types=1);

namespace App\Domain;

use App\Clock;
use App\Config\UpdatePageRepository;
use App\Http\UpdatePageRequest;
use App\Presentation\UpdatePageViewModel;
use DateTimeImmutable;

final class UpdatePageEvaluator
{
    /** @var array<string, array<string, string>> */
    private const DEFAULT_UI_TEXTS = [
        'status.available' => ['en' => 'A new version is available.'],
        'status.disabled' => ['en' => 'This update is currently unavailable.'],
        'status.upToDate' => ['en' => "You're using the latest version."],
        'status.unreleased' => ['en' => 'This update has not been released yet.'],
        'status.ended' => ['en' => 'This update period has ended.'],
        'status.unsupportedOs' => ['en' => 'This update requires a newer OS version.'],
        'status.missingDestination' => ['en' => 'This update is temporarily unavailable.'],
        'status.unavailable' => ['en' => 'This update page is currently unavailable.'],
        'button.update' => ['en' => 'Update and play the event'],
        'button.updateAriaLabel' => ['en' => 'Update to version {version} and play the event'],
        'button.prepare' => ['en' => 'Update and get ready for the event'],
        'button.prepareAriaLabel' => ['en' => 'Update to version {version} and get ready for the event'],
        'button.comingSoon' => ['en' => 'Coming Soon'],
        'notice.storeDelay' => ['en' => 'Updates may take some time to appear on the App Store or Google Play. If the update is not available yet, please try again later.'],
        'period.label' => ['en' => 'Event period: {period}'],
        'period.range' => ['en' => '{start}–{end}'],
        'period.remaining.one' => ['en' => '{range} (1 day remaining)'],
        'period.remaining.two' => ['en' => '{range} (2 days remaining)'],
        'period.remaining.few' => ['en' => '{range} ({days} days remaining)'],
        'period.remaining.many' => ['en' => '{range} ({days} days remaining)'],
        'period.remaining.other' => ['en' => '{range} ({days} days remaining)'],
        'period.startsIn.one' => ['en' => '{range} (starts in 1 day)'],
        'period.startsIn.two' => ['en' => '{range} (starts in 2 days)'],
        'period.startsIn.few' => ['en' => '{range} (starts in {days} days)'],
        'period.startsIn.many' => ['en' => '{range} (starts in {days} days)'],
        'period.startsIn.other' => ['en' => '{range} (starts in {days} days)'],
        'period.ended' => ['en' => 'Ended.'],
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
        $targetVersion = $this->repository->releaseConfig()['releaseTargetVersion'];
        $page = $this->repository->findByTargetVersion($targetVersion);
        if ($page === null) {
            return UpdatePageViewModel::unavailable(
                $request->locale,
                $this->text('status.unavailable', $request->locale),
            );
        }

        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $isBeforeEvent = $page['startAt'] !== null && $now < $page['startAt'];
        $updateButtonKey = $isBeforeEvent ? 'button.prepare' : 'button.update';
        $updateButtonAriaLabelKey = $isBeforeEvent ? 'button.prepareAriaLabel' : 'button.updateAriaLabel';
        $common = [
            'locale' => $request->locale,
            'textDirection' => $this->localeResolver->textDirection($request->locale),
            'imageUrl' => $page['imageUrl'],
            'imageAlt' => $this->localeResolver->resolve($page['imageAltTexts'], $request->locale),
            'title' => $this->localeResolver->resolve($page['title'], $request->locale),
            'description' => $this->localeResolver->resolve($page['descriptions'], $request->locale),
            'socialCardTitle' => $this->localeResolver->resolve($page['socialCard']['title'], $request->locale),
            'socialCardDescription' => $this->localeResolver->resolve($page['socialCard']['description'], $request->locale),
            'currentVersion' => $request->appVersion,
            'targetVersion' => $page['targetVersion'],
            'startAt' => $page['startAt'],
            'endAt' => $page['endAt'],
            'now' => $now,
            'template' => $page['template'],
            'updateButtonLabel' => $this->text($updateButtonKey, $request->locale),
            'updateButtonAriaLabel' => $this->text(
                $updateButtonAriaLabelKey,
                $request->locale,
                ['{version}' => $page['targetVersion']],
            ),
            'comingSoonButtonLabel' => $this->text('button.comingSoon', $request->locale),
            'storeNotice' => $this->text('notice.storeDelay', $request->locale),
        ];

        if (!$page['enabled']) {
            return $this->view($common, 'disabled', 'status.disabled');
        }
        if ($request->appVersion !== null
            && Version::fromString($request->appVersion)->compare(Version::fromString($page['targetVersion'])) >= 0
        ) {
            return $this->view($common, 'up-to-date', 'status.upToDate');
        }
        if ($page['endAt'] !== null && $now > $page['endAt']) {
            return $this->view($common, 'ended', 'status.ended');
        }

        $minimumOsVersion = $page['minimumOsVersions'][$request->platform] ?? null;
        if ($request->osVersion !== null
            && $minimumOsVersion instanceof Version
            && Version::fromString($request->osVersion, true)->isLessThan($minimumOsVersion)
        ) {
            return $this->view($common, 'unsupported-os', 'status.unsupportedOs', $request->osVersion, (string) $minimumOsVersion);
        }

        $destinationUrl = $page['destinationUrls'][$request->platform] ?? null;
        if ($destinationUrl === null) {
            return $this->view($common, 'missing-destination', 'status.missingDestination');
        }
        if (!$page['released'][$request->platform]) {
            return $this->view(
                $common,
                'unreleased',
                'status.unreleased',
                null,
                null,
                $destinationUrl,
                $request->platform !== 'pc',
            );
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
            in_array($state, ['available', 'unreleased'], true),
            $showStoreNotice && in_array($state, ['available', 'unreleased'], true),
            $common['template'],
            $this->theme ?? UpdatePageViewModel::DEFAULT_THEME,
            $this->labeledEventPeriod($common),
            $common['updateButtonLabel'],
            $common['updateButtonAriaLabel'],
            $common['storeNotice'],
            $osRequirementMessage,
            $common['comingSoonButtonLabel'],
            $common['socialCardTitle'],
            $common['socialCardDescription'],
            $common['title'],
            $common['textDirection'],
        );
    }

    /** @param array<string, mixed> $common */
    private function labeledEventPeriod(array $common): ?string
    {
        $period = $this->formatEventPeriod(
            $common['startAt'],
            $common['endAt'],
            $common['now'],
            $common['locale'],
        );

        return $period === null
            ? null
            : $this->text('period.label', $common['locale'], ['{period}' => $period]);
    }

    /** @param array<string, string> $replacements */
    private function text(string $key, string $locale, array $replacements = []): string
    {
        $texts = $this->uiTexts ?? self::DEFAULT_UI_TEXTS;
        $translations = $texts[$key] ?? self::DEFAULT_UI_TEXTS[$key];
        return strtr($this->localeResolver->resolve($translations, $locale), $replacements);
    }

    private function formatEventPeriod(
        ?DateTimeImmutable $startAt,
        ?DateTimeImmutable $endAt,
        DateTimeImmutable $now,
        string $locale,
    ): ?string {
        if ($startAt === null && $endAt === null) {
            return null;
        }
        if ($endAt !== null && $now > $endAt) {
            return $this->text('period.ended', $locale);
        }

        $range = $this->formatDateRange($startAt, $endAt, $locale);
        if ($startAt !== null && $now < $startAt) {
            $days = $this->roundedUpDaysBetween($now, $startAt);
            return $this->pluralText(
                'period.startsIn',
                $locale,
                $days,
                ['{range}' => $range, '{days}' => $this->formatNumber($days, $locale)],
            );
        }
        if ($endAt !== null) {
            $days = $this->roundedUpDaysBetween($now, $endAt);
            return $this->pluralText(
                'period.remaining',
                $locale,
                $days,
                ['{range}' => $range, '{days}' => $this->formatNumber($days, $locale)],
            );
        }

        return $range;
    }

    private function roundedUpDaysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return max(1, (int) ceil(($to->getTimestamp() - $from->getTimestamp()) / 86400));
    }

    private function formatDateRange(?DateTimeImmutable $startAt, ?DateTimeImmutable $endAt, string $locale): string
    {
        $timeZone = ($startAt ?? $endAt)?->getTimezone();
        $start = $startAt?->setTimezone($timeZone);
        $end = $endAt?->setTimezone($timeZone);
        if ($start === null) {
            return $this->formatDate($end, $locale, true);
        }
        if ($end === null) {
            return $this->formatDate($start, $locale, true);
        }

        $sameYear = $start->format('Y') === $end->format('Y');
        $sameMonth = $sameYear && $start->format('n') === $end->format('n');
        $language = $this->localeResolver->language($locale);

        if ($language === 'ja') {
            $startText = $this->formatDate($start, $locale, !$sameYear);
            $endText = $sameMonth ? $end->format('j日') : $this->formatDate($end, $locale, !$sameYear);
        } elseif (in_array($language, ['ar', 'he'], true)) {
            $startText = $this->formatDate($start, $locale, !$sameYear);
            $endText = $this->formatDate($end, $locale, !$sameYear);
        } else {
            $startText = $this->formatDate($start, $locale, !$sameYear);
            $endText = $sameMonth ? $end->format('j') : $this->formatDate($end, $locale, !$sameYear);
        }

        return $this->text('period.range', $locale, ['{start}' => $startText, '{end}' => $endText]);
    }

    private function formatDate(?DateTimeImmutable $date, string $locale, bool $includeYear): string
    {
        if ($date === null) {
            return '';
        }

        $language = $this->localeResolver->language($locale);
        if ($language === 'ja') {
            return ($includeYear ? $date->format('Y年') : '') . $date->format('n月j日');
        }
        if (in_array($language, ['ar', 'he'], true)) {
            $pattern = match ($language) {
                'ar' => $includeYear ? 'd MMMM y' : 'd MMMM',
                'he' => $includeYear ? 'd בMMMM y' : 'd בMMMM',
            };
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                $this->intlTimeZone($date),
                \IntlDateFormatter::GREGORIAN,
                $pattern,
            );
            $formatted = $formatter->format($date);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $formatted = $months[(int) $date->format('n') - 1] . ' ' . $date->format('j');
        return $includeYear ? $formatted . ', ' . $date->format('Y') : $formatted;
    }

    /** @param array<string, string> $replacements */
    private function pluralText(string $baseKey, string $locale, int $value, array $replacements): string
    {
        $texts = $this->uiTexts ?? self::DEFAULT_UI_TEXTS;
        $key = $baseKey . '.' . $this->pluralCategory($value, $locale);
        if (!array_key_exists($key, $texts)) {
            $key = $baseKey . '.other';
        }

        return $this->text($key, $locale, $replacements);
    }

    private function pluralCategory(int $value, string $locale): string
    {
        $language = $this->localeResolver->language($locale);
        if ($language === 'ar') {
            if ($value === 1) {
                return 'one';
            }
            if ($value === 2) {
                return 'two';
            }

            $moduloHundred = $value % 100;
            if ($moduloHundred >= 3 && $moduloHundred <= 10) {
                return 'few';
            }
            if ($moduloHundred >= 11 && $moduloHundred <= 99) {
                return 'many';
            }
        }
        if ($language === 'he' && $value === 2) {
            return 'two';
        }

        return $value === 1 ? 'one' : 'other';
    }

    private function formatNumber(int $value, string $locale): string
    {
        if (!in_array($this->localeResolver->language($locale), ['ar', 'he'], true)) {
            return (string) $value;
        }

        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatted = $formatter->format($value);
        return $formatted === false ? (string) $value : $formatted;
    }

    private function intlTimeZone(DateTimeImmutable $date): string
    {
        $timeZone = $date->getTimezone()->getName();
        return preg_match('/^[+-]\d{2}:\d{2}$/D', $timeZone) === 1
            ? 'GMT' . $timeZone
            : $timeZone;
    }
}
