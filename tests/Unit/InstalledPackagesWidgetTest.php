<?php

declare(strict_types=1);

use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Data\OpsPackageSummary;
use YezzMedia\Ops\Support\OpsPackageSummaryCacheManager;
use YezzMedia\Ops\Widgets\InstalledPackagesWidget;

it('builds operator-facing package stats from the package summary', function (): void {
    app(OpsPackageSummaryCacheManager::class)->store(new OpsPackageSummary(
        installedCount: 4,
        enabledCount: 3,
        disabledCount: 1,
        featurePackageCount: 2,
        status: 'warnings',
    ));

    $widget = new class extends InstalledPackagesWidget
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
        ->and($stats[0]->getLabel())->toBe('Installed packages')
        ->and($stats[0]->getValue())->toBe(4)
        ->and($stats[1]->getLabel())->toBe('Enabled packages')
        ->and($stats[1]->getValue())->toBe(3)
        ->and($stats[2]->getLabel())->toBe('Status')
        ->and($stats[2]->getValue())->toBe('Warnings');
});

it('shows an unconfigured package state when no packages are registered', function (): void {
    app(OpsPackageSummaryCacheManager::class)->store(new OpsPackageSummary(
        installedCount: 0,
        enabledCount: 0,
        disabledCount: 0,
        featurePackageCount: 0,
        status: 'empty',
    ));

    $widget = new class extends InstalledPackagesWidget
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

    expect($stats[2]->getValue())->toBe('Unconfigured')
        ->and($stats[2]->getDescription())->toBe('No platform packages are currently registered.');
});
