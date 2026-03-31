<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;
use YezzMedia\Ops\Actions\RunSystemDiagnosticsAction;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;
use YezzMedia\Ops\Support\OpsDiagnosticsSummaryResolver;
use YezzMedia\Ops\Support\OpsRuntimePostureResolver;

/**
 * Shows diagnostics posture and curated runtime visibility for operators.
 */
final class SystemHealthPage extends OpsPage
{
    protected static string $opsSurface = 'diagnostics';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Diagnostics';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'diagnostics';

    protected static ?string $title = 'System health';

    protected string $view = 'ops::pages.system-health-page';

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
     * @var list<array{title: string, items: list<array{label: string, value: string, description: string}>}>
     */
    public array $runtime = [];

    public bool $showsRuntime = false;

    public function mount(): void
    {
        $this->refreshState();
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('runDiagnostics')
                ->label('Run diagnostics')
                ->icon(Heroicon::OutlinedArrowPath)
                ->authorize(fn (): bool => app(OpsAuthorizationResolver::class)->canAccessSurface('diagnostics'))
                ->action(function (): void {
                    $this->runDiagnostics();
                }),
        ];
    }

    public function runDiagnostics(): void
    {
        try {
            app(RunSystemDiagnosticsAction::class)->run();
            $this->refreshState();

            Notification::make()
                ->title('Diagnostics refreshed')
                ->body(sprintf(
                    'Latest posture: %d failing check(s), %d warning(s), %d passed check(s).',
                    $this->summary['failingCount'],
                    $this->summary['warningCount'],
                    $this->summary['passedCount'],
                ))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Diagnostics refresh failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function refreshState(): void
    {
        $summary = app(OpsDiagnosticsSummaryResolver::class)->collect();

        $this->summary = [
            'status' => $summary->status,
            'failingCount' => $summary->failingCount,
            'warningCount' => $summary->warningCount,
            'passedCount' => $summary->passedCount,
            'skippedCount' => $summary->skippedCount,
            'completedAt' => $summary->completedAt,
            'accessMode' => $summary->accessMode,
            'healthInstalled' => $summary->healthInstalled,
            'auditInstalled' => $summary->auditInstalled,
            'checks' => array_values($summary->checks),
        ];
        $this->showsRuntime = app(OpsAuthorizationResolver::class)->canAccessSurface('runtime');
        $this->runtime = $this->showsRuntime
            ? app(OpsRuntimePostureResolver::class)->resolve()
            : [];
    }
}
