<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Data\OpsDiagnosticsSummary;
use YezzMedia\Ops\Support\OpsFailingChecksWidgetDataResolver;

/**
 * Surfaces compact failing and warning diagnostics posture for operators.
 */
class FailingChecksWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Diagnostics';

    protected ?string $description = 'Failing and warning checks that need operator attention.';

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(OpsFailingChecksWidgetDataResolver::class)->resolve();

        return [
            Stat::make('Failing checks', $summary->failingCount)
                ->description($summary->failingCount > 0 ? 'Blocking diagnostics need attention.' : 'No failing checks were reported.')
                ->descriptionIcon($summary->failingCount > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($summary->failingCount > 0 ? 'danger' : 'success'),
            Stat::make('Warnings', $summary->warningCount)
                ->description($summary->warningCount > 0 ? 'Warnings were reported by the latest diagnostics run.' : 'No warning checks were reported.')
                ->descriptionIcon($summary->warningCount > 0 ? Heroicon::OutlinedExclamationCircle : Heroicon::OutlinedCheckCircle)
                ->color($summary->warningCount > 0 ? 'warning' : 'success'),
            Stat::make('Status', $this->statusLabel($summary))
                ->description($this->statusDescription($summary))
                ->descriptionIcon($this->statusIcon($summary))
                ->color($this->statusColor($summary)),
        ];
    }

    private function statusLabel(OpsDiagnosticsSummary $summary): string
    {
        if ($summary->status !== 'completed') {
            return 'Unavailable';
        }

        if ($summary->failingCount > 0) {
            return 'Degraded';
        }

        if ($summary->warningCount > 0) {
            return 'Warnings';
        }

        return 'Healthy';
    }

    private function statusDescription(OpsDiagnosticsSummary $summary): string
    {
        if ($summary->status !== 'completed') {
            return 'The latest diagnostics refresh did not complete successfully.';
        }

        if ($summary->healthInstalled) {
            return 'Foundation doctor and health integrations are available.';
        }

        return 'Foundation doctor results are available for operator review.';
    }

    private function statusIcon(OpsDiagnosticsSummary $summary): Heroicon
    {
        if ($summary->status !== 'completed') {
            return Heroicon::OutlinedClock;
        }

        if ($summary->failingCount > 0) {
            return Heroicon::OutlinedExclamationTriangle;
        }

        if ($summary->warningCount > 0) {
            return Heroicon::OutlinedExclamationCircle;
        }

        return Heroicon::OutlinedCheckCircle;
    }

    private function statusColor(OpsDiagnosticsSummary $summary): string
    {
        if ($summary->status !== 'completed') {
            return 'gray';
        }

        if ($summary->failingCount > 0) {
            return 'danger';
        }

        if ($summary->warningCount > 0) {
            return 'warning';
        }

        return 'success';
    }
}
