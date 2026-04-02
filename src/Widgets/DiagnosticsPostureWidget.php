<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Surfaces the latest diagnostics refresh posture for operators.
 */
class DiagnosticsPostureWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Diagnostics posture';

    protected ?string $description = 'Latest refresh timing, successful checks, and access mode for diagnostics.';

    protected ?string $pollingInterval = null;

    /**
     * @var array{status: string, failingCount: int, warningCount: int, passedCount: int, skippedCount: int, completedAt: string, accessMode: string, healthInstalled: bool, auditInstalled: bool, checks: list<array{key: string, package: string, status: string, message: string, isBlocking: bool}>}
     */
    public array $summary = [
        'status' => 'idle',
        'failingCount' => 0,
        'warningCount' => 0,
        'passedCount' => 0,
        'skippedCount' => 0,
        'completedAt' => '',
        'accessMode' => 'reduced',
        'healthInstalled' => false,
        'auditInstalled' => false,
        'checks' => [],
    ];

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $completedAt = $this->summary['completedAt'] !== '' ? $this->summary['completedAt'] : 'n/a';

        return [
            Stat::make('Passed checks', $this->summary['passedCount'])
                ->description('Checks that completed successfully during the latest diagnostics refresh.'),
            Stat::make('Skipped checks', $this->summary['skippedCount'])
                ->description('Checks intentionally skipped by the latest diagnostics refresh.'),
            Stat::make('Last refresh', $completedAt)
                ->description(sprintf('Mode: %s', str($this->summary['accessMode'])->headline()->toString())),
        ];
    }
}
