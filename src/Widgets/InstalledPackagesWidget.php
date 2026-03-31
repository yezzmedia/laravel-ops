<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Data\OpsPackageSummary;
use YezzMedia\Ops\Support\OpsPackageSummaryResolver;

/**
 * Surfaces compact package visibility on the ops dashboard.
 */
class InstalledPackagesWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Packages';

    protected ?string $description = 'Installed platform packages and coarse package posture.';

    protected ?string $pollingInterval = null;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(OpsPackageSummaryResolver::class)->resolve();

        return [
            Stat::make('Installed packages', $summary->installedCount)
                ->description('Platform-visible packages registered through foundation.')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($summary->installedCount > 0 ? 'success' : 'gray'),
            Stat::make('Enabled packages', $summary->enabledCount)
                ->description($summary->disabledCount > 0 ? 'Some registered packages are disabled.' : 'All registered packages are enabled.')
                ->descriptionIcon($summary->disabledCount > 0 ? Heroicon::OutlinedExclamationCircle : Heroicon::OutlinedCheckCircle)
                ->color($summary->disabledCount > 0 ? 'warning' : 'success'),
            Stat::make('Status', $this->statusLabel($summary))
                ->description($this->statusDescription($summary))
                ->descriptionIcon($this->statusIcon($summary))
                ->color($this->statusColor($summary)),
        ];
    }

    private function statusLabel(OpsPackageSummary $summary): string
    {
        return match ($summary->status) {
            'empty' => 'Unconfigured',
            'warnings' => 'Warnings',
            default => 'Healthy',
        };
    }

    private function statusDescription(OpsPackageSummary $summary): string
    {
        return match ($summary->status) {
            'empty' => 'No platform packages are currently registered.',
            'warnings' => sprintf('%d package(s) expose features and %d package(s) are disabled.', $summary->featurePackageCount, $summary->disabledCount),
            default => sprintf('%d package(s) expose registered platform features.', $summary->featurePackageCount),
        };
    }

    private function statusIcon(OpsPackageSummary $summary): Heroicon
    {
        return match ($summary->status) {
            'empty' => Heroicon::OutlinedClock,
            'warnings' => Heroicon::OutlinedExclamationCircle,
            default => Heroicon::OutlinedCheckCircle,
        };
    }

    private function statusColor(OpsPackageSummary $summary): string
    {
        return match ($summary->status) {
            'empty' => 'gray',
            'warnings' => 'warning',
            default => 'success',
        };
    }
}
