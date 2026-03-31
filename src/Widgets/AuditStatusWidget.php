<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Surfaces audit posture details that complement recent activity stats.
 */
class AuditStatusWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Audit posture';

    protected ?string $description = 'Current audit backend posture and latest recorded audit timestamp.';

    protected ?string $pollingInterval = null;

    /**
     * @var array{status: string, backend: ?string, activityCount: int, latestDescription: ?string, latestAt: ?string, items: list<array{description: string, event: ?string, logName: ?string, loggedAt: ?string}>}
     */
    public array $summary = [
        'status' => 'unavailable',
        'backend' => null,
        'activityCount' => 0,
        'latestDescription' => null,
        'latestAt' => null,
        'items' => [],
    ];

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Status', str($this->summary['status'])->headline()->toString())
                ->description($this->statusDescription()),
            Stat::make('Latest logged at', $this->summary['latestAt'] ?? 'n/a')
                ->description($this->summary['latestDescription'] ?? $this->latestFallbackDescription()),
        ];
    }

    private function statusDescription(): string
    {
        return match ($this->summary['status']) {
            'unavailable' => 'No supported audit backend is currently installed.',
            'degraded' => 'The audit backend is present, but recent activity could not be read.',
            'empty' => 'The audit backend is available, but no recent entries were returned.',
            default => 'Recent audit activity is available for operator review.',
        };
    }

    private function latestFallbackDescription(): string
    {
        return match ($this->summary['status']) {
            'unavailable' => 'Install a supported audit backend to surface recent audit activity.',
            'degraded' => 'The latest audit timestamp is unavailable because recent activity could not be read.',
            default => 'No recent audit timestamp is currently available.',
        };
    }
}
