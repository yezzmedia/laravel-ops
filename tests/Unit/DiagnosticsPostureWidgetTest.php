<?php

declare(strict_types=1);

use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Widgets\DiagnosticsPostureWidget;

it('builds operator-facing diagnostics posture stats from page summary data', function (): void {
    $widget = new class extends DiagnosticsPostureWidget
    {
        /**
         * @return array<Stat>
         */
        public function exposedStats(): array
        {
            return $this->getStats();
        }
    };

    $widget->summary = [
        'status' => 'completed',
        'failingCount' => 1,
        'warningCount' => 2,
        'passedCount' => 5,
        'skippedCount' => 1,
        'completedAt' => '2026-03-31T12:00:00+00:00',
        'accessMode' => 'access_integrated',
        'healthInstalled' => true,
        'auditInstalled' => true,
        'checks' => [],
    ];

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getLabel())->toBe('Passed checks')
        ->and($stats[0]->getValue())->toBe(5)
        ->and($stats[1]->getLabel())->toBe('Skipped checks')
        ->and($stats[1]->getValue())->toBe(1)
        ->and($stats[2]->getLabel())->toBe('Last refresh')
        ->and($stats[2]->getValue())->toBe('2026-03-31T12:00:00+00:00')
        ->and($stats[2]->getDescription())->toBe('Mode: Access Integrated');
});
