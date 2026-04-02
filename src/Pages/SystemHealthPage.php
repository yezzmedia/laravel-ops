<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;
use YezzMedia\Ops\Actions\RunSystemDiagnosticsAction;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;
use YezzMedia\Ops\Support\OpsDiagnosticsSummaryResolver;
use YezzMedia\Ops\Support\OpsRuntimePostureResolver;
use YezzMedia\Ops\Widgets\ApplicationRuntimeWidget;
use YezzMedia\Ops\Widgets\DiagnosticsPostureWidget;
use YezzMedia\Ops\Widgets\DriversRuntimeWidget;
use YezzMedia\Ops\Widgets\FailingChecksWidget;
use YezzMedia\Ops\Widgets\IntegrationsRuntimeWidget;

/**
 * Shows diagnostics posture and curated runtime visibility for operators.
 */
final class SystemHealthPage extends OpsPage implements HasTable
{
    use InteractsWithTable;

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

    public function table(Table $table): Table
    {
        return $table
            ->heading('Doctor checks')
            ->description('Curated diagnostics posture from approved health sources.')
            ->records(function (?string $sortColumn, ?string $sortDirection, ?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                $records = $this->checkRecords();

                if (filled($search)) {
                    $needle = mb_strtolower(trim((string) $search));

                    $records = $records->filter(static function (array $record) use ($needle): bool {
                        return str_contains(mb_strtolower($record['key']), $needle)
                            || str_contains(mb_strtolower($record['package']), $needle)
                            || str_contains(mb_strtolower($record['status']), $needle)
                            || str_contains(mb_strtolower($record['message']), $needle);
                    })->values();
                }

                $sortColumn ??= 'key';
                $sortDirection ??= 'asc';

                $records = $records
                    ->sortBy($sortColumn, SORT_NATURAL, $sortDirection === 'desc')
                    ->values();

                return new LengthAwarePaginator(
                    items: $records->forPage($page, $recordsPerPage)->values(),
                    total: $records->count(),
                    perPage: $recordsPerPage,
                    currentPage: $page,
                );
            })
            ->defaultSort('key')
            ->searchable()
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('key')
                    ->label('Check')
                    ->description(fn (array $record): string => $record['isBlocking'] ? 'Blocking doctor check' : 'Non-blocking doctor check')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('package')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(static fn (string $state): string => str($state)->headline()->toString())
                    ->color(static function (string $state): string {
                        return match ($state) {
                            'failed' => 'danger',
                            'warning' => 'warning',
                            'passed' => 'success',
                            default => 'gray',
                        };
                    })
                    ->sortable(),
                TextColumn::make('message')
                    ->wrap()
                    ->searchable(),
            ])
            ->emptyStateHeading('No doctor checks are currently available.');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FailingChecksWidget::class,
            DiagnosticsPostureWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        if (! $this->showsRuntime) {
            return [];
        }

        return [
            ApplicationRuntimeWidget::class,
            DriversRuntimeWidget::class,
            IntegrationsRuntimeWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): array
    {
        return [
            'md' => 1,
            'xl' => 2,
        ];
    }

    public function getFooterWidgetsColumns(): array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'summary' => $this->summary,
            'runtime' => $this->runtime,
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

    /**
     * @return Collection<int, array{key: string, package: string, status: string, message: string, isBlocking: bool}>
     */
    private function checkRecords(): Collection
    {
        return collect($this->summary['checks']);
    }
}
