<?php

declare(strict_types=1);

namespace App\Domain;

use App\Clock;
use App\Config\ConfigException;
use App\Config\UpdatePageRepository;
use DateTimeImmutable;
use DateTimeZone;

final readonly class PublicReleaseStatus
{
    public function __construct(
        private UpdatePageRepository $repository,
        private Clock $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        $release = $this->repository->releaseConfig();
        $page = $this->repository->findByTargetVersion($release['releaseTargetVersion']);
        if ($page === null) {
            throw new ConfigException('Configuration is invalid.');
        }

        $platforms = [];
        foreach (['ios', 'android', 'pc'] as $platform) {
            $platforms[$platform] = [
                'released' => $page['released'][$platform],
                'targetUrl' => $page['destinationUrls'][$platform] ?? null,
            ];
        }

        return [
            'schemaVersion' => 1,
            'releaseVersion' => $page['targetVersion'],
            'enabled' => $page['enabled'],
            'eventPeriod' => [
                'startAt' => $this->formatDate($page['startAt']),
                'endAt' => $this->formatDate($page['endAt']),
                'phase' => $this->eventPhase($page['startAt'], $page['endAt']),
            ],
            'platforms' => $platforms,
        ];
    }

    private function eventPhase(?DateTimeImmutable $startAt, ?DateTimeImmutable $endAt): string
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        if ($startAt !== null && $now < $startAt) {
            return 'upcoming';
        }
        if ($endAt !== null && $now > $endAt) {
            return 'ended';
        }

        return 'active';
    }

    private function formatDate(?DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d\TH:i:sP');
    }
}
