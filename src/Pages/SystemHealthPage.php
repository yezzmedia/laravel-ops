<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
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
     * @var array{status: string, failingCount: int, warningCount: int, passedCount: int, skippedCount: int, completedAt: string, accessMode: string, healthInstalled: bool, auditInstalled: bool, checks: list<array{key: string, package: string, status: string, message: string, isBlocking: bool, context: array<string, mixed>|null}>}
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
            ->recordUrl(fn (array $record): string => DoctorCheckDetailsPage::getUrl([
                'package' => $record['package'],
                'check' => $record['key'],
            ]))
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

    public function heroData(): array
    {
        return [
            'eyebrow' => 'Ops diagnostics',
            'heading' => 'System health posture',
            'description' => 'Review the current doctor status, current integration posture, and whether the shared runtime surfaces are available to operators.',
            'metrics' => [
                [
                    'label' => 'Status',
                    'value' => str($this->summary['status'])->headline()->toString(),
                    'helperText' => 'Current diagnostics posture.',
                    'display' => 'badge',
                    'tone' => $this->diagnosticsStatusTone(),
                ],
                [
                    'label' => 'Failing checks',
                    'value' => $this->summary['failingCount'],
                    'helperText' => 'Blocking or degraded checks.',
                    'display' => 'numeric',
                    'tone' => 'danger',
                ],
                [
                    'label' => 'Warnings',
                    'value' => $this->summary['warningCount'],
                    'helperText' => 'Checks surfaced as warnings.',
                    'display' => 'numeric',
                    'tone' => 'warning',
                ],
                [
                    'label' => 'Passed checks',
                    'value' => $this->summary['passedCount'],
                    'helperText' => 'Checks that currently pass.',
                    'display' => 'numeric',
                    'tone' => 'success',
                ],
            ],
            'actions' => [],
        ];
    }

    public function diagnosticsHeroInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->heroData())
            ->components([
                Section::make('System health posture')
                    ->description('Review the current doctor status, current integration posture, and whether the shared runtime surfaces are available to operators.')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->iconColor('primary')
                    ->afterHeader([
                        TextEntry::make('eyebrow')
                            ->hiddenLabel()
                            ->badge()
                            ->icon(Heroicon::OutlinedSparkles)
                            ->color('primary'),
                        TextEntry::make('status')
                            ->hiddenLabel()
                            ->badge()
                            ->color(fn (string $state): string => $this->heroData()['statusTone']),
                    ])
                    ->schema([
                        TextEntry::make('failingCount')
                            ->label('Failing checks')
                            ->numeric()
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->iconColor('danger')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->helperText('Blocking or degraded checks that currently require operator attention.'),
                        TextEntry::make('warningCount')
                            ->label('Warnings')
                            ->numeric()
                            ->icon(Heroicon::OutlinedExclamationCircle)
                            ->iconColor('warning')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->helperText('Checks that surfaced warnings without fully failing the current posture.'),
                        TextEntry::make('passedCount')
                            ->label('Passed checks')
                            ->numeric()
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->iconColor('success')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->helperText('Checks that currently pass against the approved diagnostics surface.'),
                    ])
                    ->columns(3),
            ]);
    }

    public function diagnosticsRuntimeInfolist(Schema $schema): Schema
    {
        return $schema
            ->components($this->runtimeSections());
    }

    public function diagnosticsDetailInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->heroData())
            ->components([
                Section::make('Diagnostics context')
                    ->description('Additional operational context for the current diagnostics snapshot and shared integrations.')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->schema([
                        TextEntry::make('completedAt')
                            ->label('Last completed at')
                            ->helperText('Timestamp of the latest collected diagnostics snapshot.'),
                        TextEntry::make('skippedCount')
                            ->label('Skipped checks')
                            ->numeric()
                            ->icon(Heroicon::OutlinedMinusCircle)
                            ->iconColor('gray')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->helperText('Checks that were skipped in the current diagnostics collection.'),
                        TextEntry::make('accessMode')
                            ->label('Access mode')
                            ->badge()
                            ->color('gray')
                            ->helperText('Whether diagnostics currently run in reduced mode or access-integrated mode.'),
                        TextEntry::make('healthInstalled')
                            ->label('Health backend')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Installed' ? 'success' : 'gray')
                            ->helperText('Availability of the shared health integration that feeds doctor checks.'),
                        TextEntry::make('auditInstalled')
                            ->label('Audit backend')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Installed' ? 'success' : 'gray')
                            ->helperText('Availability of the audit integration surfaced alongside diagnostics posture.'),
                    ])
                    ->columns(3),
            ]);
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
     * @return Collection<int, array{key: string, package: string, status: string, message: string, isBlocking: bool, context: array<string, mixed>|null}>
     */
    private function checkRecords(): Collection
    {
        return collect($this->summary['checks']);
    }

    private function diagnosticsStatusTone(): string
    {
        if ($this->summary['failingCount'] > 0) {
            return 'danger';
        }

        if ($this->summary['warningCount'] > 0) {
            return 'warning';
        }

        if ($this->summary['passedCount'] > 0) {
            return 'success';
        }

        return 'gray';
    }

    /**
     * @return list<array{title: string, description: string, items: list<array{label: string, value: string, description: string}>}>
     */
    public function runtimeSectionBlueprints(): array
    {
        return self::runtimeSectionBlueprintsFor($this->runtime);
    }

    /**
     * @param  list<array{title: string, items: list<array{label: string, value: string, description: string}>}>  $runtime
     * @return list<array{title: string, description: string, items: list<array{label: string, value: string, description: string}>}>
     */
    public static function runtimeSectionBlueprintsFor(array $runtime): array
    {
        return collect($runtime)
            ->map(static function (array $section): array {
                return [
                    'title' => $section['title'],
                    'description' => self::runtimeSectionDescriptionFor($section['title']),
                    'items' => $section['items'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<Section>
     */
    private function runtimeSections(): array
    {
        return collect($this->runtimeSectionBlueprints())
            ->map(function (array $section): Section {
                return Section::make($section['title'])
                    ->description($section['description'])
                    ->icon(Heroicon::OutlinedCpuChip)
                    ->schema([
                        RepeatableEntry::make('items')
                            ->state($section['items'])
                            ->contained(false)
                            ->table([
                                TableColumn::make('Label'),
                                TableColumn::make('Value'),
                                TableColumn::make('Description'),
                            ])
                            ->schema([
                                TextEntry::make('label'),
                                TextEntry::make('value')
                                    ->badge()
                                    ->color(fn (string $state): string => $this->runtimeValueTone($state)),
                                TextEntry::make('description')
                                    ->wrap(),
                            ])
                            ->placeholder('Runtime posture data is currently unavailable.'),
                    ]);
            })
            ->values()
            ->all();
    }

    private static function runtimeSectionDescriptionFor(string $title): string
    {
        return match ($title) {
            'Application' => 'Application environment, debug posture, and resolved ops guard state.',
            'Drivers' => 'Default host drivers that influence operator-facing runtime behavior.',
            'Integrations' => 'Installed package integrations that enrich diagnostics and operator visibility.',
            default => 'Runtime posture details for this section.',
        };
    }

    private function runtimeValueTone(string $value): string
    {
        return match ($value) {
            'Installed', 'Access integrated', 'Enabled' => 'success',
            'Unavailable', 'Reduced mode', 'Disabled' => 'gray',
            default => 'gray',
        };
    }
}
