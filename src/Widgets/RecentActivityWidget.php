<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Data\OpsRecentActivitySummary;
use YezzMedia\Ops\Support\OpsRecentActivityResolver;

/**
 * Surfaces compact recent activity visibility for the ops dashboard.
 */
class RecentActivityWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Recent activity';

    protected ?string $description = 'Recent operator-visible activity when a supported backend is available.';

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(OpsRecentActivityResolver::class)->resolve();

        return [
            Stat::make('Recent entries', $summary->activityCount)
                ->description($this->entriesDescription($summary))
                ->descriptionIcon($this->entriesIcon($summary))
                ->color($this->entriesColor($summary)),
            Stat::make('Latest event', $summary->latestDescription ?? $this->latestFallback($summary))
                ->description($this->latestDescription($summary))
                ->descriptionIcon($this->latestIcon($summary))
                ->color($this->latestColor($summary)),
            Stat::make('Backend', $summary->backend !== null ? 'Activitylog' : 'Unavailable')
                ->description($this->backendDescription($summary))
                ->descriptionIcon($this->backendIcon($summary))
                ->color($this->backendColor($summary)),
        ];
    }

    private function entriesDescription(OpsRecentActivitySummary $summary): string
    {
        return match ($summary->status) {
            'unavailable' => 'No supported audit or activity backend is installed.',
            'degraded' => 'The audit backend is present but recent activity could not be read.',
            'empty' => 'No recent activity is currently available.',
            default => 'Recent operator-visible activity is available.',
        };
    }

    private function entriesIcon(OpsRecentActivitySummary $summary): Heroicon
    {
        return match ($summary->status) {
            'unavailable' => Heroicon::OutlinedClock,
            'degraded' => Heroicon::OutlinedExclamationTriangle,
            'empty' => Heroicon::OutlinedCheckCircle,
            default => Heroicon::OutlinedCheckCircle,
        };
    }

    private function entriesColor(OpsRecentActivitySummary $summary): string
    {
        return match ($summary->status) {
            'unavailable' => 'gray',
            'degraded' => 'warning',
            default => 'success',
        };
    }

    private function latestFallback(OpsRecentActivitySummary $summary): string
    {
        return match ($summary->status) {
            'unavailable' => 'Unavailable',
            'degraded' => 'Unavailable',
            'empty' => 'No activity',
            default => 'No activity',
        };
    }

    private function latestDescription(OpsRecentActivitySummary $summary): string
    {
        if ($summary->latestAt !== null) {
            return sprintf('Latest activity at %s.', $summary->latestAt);
        }

        return match ($summary->status) {
            'unavailable' => 'Install a supported audit backend to surface recent activity.',
            'degraded' => 'The audit backend is installed but the latest activity could not be read.',
            default => 'No recent activity entry is currently available.',
        };
    }

    private function latestIcon(OpsRecentActivitySummary $summary): Heroicon
    {
        return match ($summary->status) {
            'available' => Heroicon::OutlinedCheckCircle,
            'empty' => Heroicon::OutlinedClock,
            'degraded' => Heroicon::OutlinedExclamationTriangle,
            default => Heroicon::OutlinedClock,
        };
    }

    private function latestColor(OpsRecentActivitySummary $summary): string
    {
        return match ($summary->status) {
            'available' => 'success',
            'degraded' => 'warning',
            default => 'gray',
        };
    }

    private function backendDescription(OpsRecentActivitySummary $summary): string
    {
        return match ($summary->status) {
            'unavailable' => 'No supported audit or activity backend is available.',
            'degraded' => 'Activitylog is installed but the recent activity summary is degraded.',
            'empty' => 'Activitylog is available but no recent entries were returned.',
            default => 'Activitylog is supplying recent operator-visible activity.',
        };
    }

    private function backendIcon(OpsRecentActivitySummary $summary): Heroicon
    {
        return match ($summary->status) {
            'unavailable' => Heroicon::OutlinedClock,
            'degraded' => Heroicon::OutlinedExclamationTriangle,
            default => Heroicon::OutlinedCheckCircle,
        };
    }

    private function backendColor(OpsRecentActivitySummary $summary): string
    {
        return match ($summary->status) {
            'available', 'empty' => 'success',
            'degraded' => 'warning',
            default => 'gray',
        };
    }
}
