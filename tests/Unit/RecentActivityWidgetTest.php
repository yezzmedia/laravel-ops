<?php

declare(strict_types=1);

use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Data\OpsRecentActivityItem;
use YezzMedia\Ops\Data\OpsRecentActivitySummary;
use YezzMedia\Ops\Support\OpsRecentActivityCacheManager;
use YezzMedia\Ops\Widgets\RecentActivityWidget;

it('builds operator-facing stats from recent activity data', function (): void {
    app(OpsRecentActivityCacheManager::class)->store(new OpsRecentActivitySummary(
        status: 'available',
        backend: 'activitylog',
        activityCount: 2,
        latestDescription: 'Permissions synchronized.',
        latestAt: now()->toIso8601String(),
        items: [
            new OpsRecentActivityItem('Permissions synchronized.', 'updated', 'ops', now()->toIso8601String(), actorLabel: 'User #1', subjectLabel: 'Role #2', contextPreview: 'role=super-admin', contextRows: [], changesRows: []),
        ],
    ));

    $widget = new class extends RecentActivityWidget
    {
        /**
         * @return array<Stat>
         */
        public function exposedStats(): array
        {
            return $this->getStats();
        }
    };

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getLabel())->toBe('Recent entries')
        ->and($stats[0]->getValue())->toBe(2)
        ->and($stats[1]->getLabel())->toBe('Latest event')
        ->and($stats[1]->getValue())->toBe('Permissions synchronized.')
        ->and($stats[2]->getLabel())->toBe('Backend')
        ->and($stats[2]->getValue())->toBe('Activitylog');
});

it('shows an unavailable backend posture when no activity backend exists', function (): void {
    app(OpsRecentActivityCacheManager::class)->store(new OpsRecentActivitySummary(
        status: 'unavailable',
        backend: null,
        activityCount: 0,
        latestDescription: null,
        latestAt: null,
        items: [],
    ));

    $widget = new class extends RecentActivityWidget
    {
        /**
         * @return array<Stat>
         */
        public function exposedStats(): array
        {
            return $this->getStats();
        }
    };

    $stats = $widget->exposedStats();

    expect($stats[1]->getValue())->toBe('Unavailable')
        ->and($stats[2]->getValue())->toBe('Unavailable')
        ->and($stats[2]->getDescription())->toBe('No supported audit or activity backend is available.');
});
