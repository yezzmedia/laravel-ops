<?php

declare(strict_types=1);

use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Data\OpsDiagnosticsSummary;
use YezzMedia\Ops\Support\OpsDiagnosticsCacheManager;
use YezzMedia\Ops\Widgets\FailingChecksWidget;

it('builds operator-facing stats from the failing checks summary', function (): void {
    app(OpsDiagnosticsCacheManager::class)->storeFailingChecksSummary(new OpsDiagnosticsSummary(
        status: 'completed',
        failingCount: 2,
        warningCount: 1,
        passedCount: 4,
        skippedCount: 0,
        completedAt: now()->toIso8601String(),
        accessMode: 'access_integrated',
        healthInstalled: true,
        auditInstalled: true,
        checks: [],
    ));

    $widget = new class extends FailingChecksWidget
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
        ->and($stats[0]->getLabel())->toBe('Failing checks')
        ->and($stats[0]->getValue())->toBe(2)
        ->and($stats[1]->getLabel())->toBe('Warnings')
        ->and($stats[1]->getValue())->toBe(1)
        ->and($stats[2]->getLabel())->toBe('Status')
        ->and($stats[2]->getValue())->toBe('Degraded');
});

it('shows an unavailable status when diagnostics are degraded', function (): void {
    app(OpsDiagnosticsCacheManager::class)->storeFailingChecksSummary(new OpsDiagnosticsSummary(
        status: 'failed',
        failingCount: 0,
        warningCount: 0,
        passedCount: 0,
        skippedCount: 0,
        completedAt: now()->toIso8601String(),
        accessMode: 'reduced',
        healthInstalled: false,
        auditInstalled: false,
        checks: [],
    ));

    $widget = new class extends FailingChecksWidget
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

    expect($stats[2]->getValue())->toBe('Unavailable')
        ->and($stats[2]->getDescription())->toBe('The latest diagnostics refresh did not complete successfully.');
});
