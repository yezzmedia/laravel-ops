<?php

declare(strict_types=1);

use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Widgets\AuditStatusWidget;

it('builds operator-facing audit posture stats from page summary data', function (): void {
    $widget = new class extends AuditStatusWidget
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
        'status' => 'available',
        'backend' => 'activitylog',
        'activityCount' => 2,
        'latestDescription' => 'Permissions synchronized.',
        'latestAt' => '2026-03-31T12:00:00+00:00',
        'items' => [],
    ];

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(2)
        ->and($stats[0]->getLabel())->toBe('Status')
        ->and($stats[0]->getValue())->toBe('Available')
        ->and($stats[1]->getLabel())->toBe('Latest logged at')
        ->and($stats[1]->getValue())->toBe('2026-03-31T12:00:00+00:00')
        ->and($stats[1]->getDescription())->toBe('Permissions synchronized.');
});

it('shows unavailable audit posture details when no backend exists', function (): void {
    $widget = new class extends AuditStatusWidget
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

    expect($stats)->toHaveCount(2)
        ->and($stats[0]->getValue())->toBe('Unavailable')
        ->and($stats[0]->getDescription())->toBe('No supported audit backend is currently installed.')
        ->and($stats[1]->getValue())->toBe('n/a');
});
