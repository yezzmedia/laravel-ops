<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use YezzMedia\Ops\Actions\RefreshAuditSnapshotAction;
use YezzMedia\Ops\Data\OpsRecentActivityItem;
use YezzMedia\Ops\Support\OpsRecentActivityResolver;

/**
 * Surfaces recent operator-visible audit activity when a backend exists.
 */
final class AuditTrailPage extends OpsPage implements HasTable
{
    use InteractsWithTable;

    protected static string $opsSurface = 'audit';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Audit';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'audit';

    protected static ?string $title = 'Audit trail';

    protected string $view = 'ops::pages.audit-trail-page';

    /**
     * @var array{status: string, backend: ?string, activityCount: int, latestDescription: ?string, latestAt: ?string, cachedAt: ?string, source: ?string, items: list<array{id: string, description: string, event: ?string, logName: ?string, loggedAt: ?string, actorLabel: string, subjectLabel: string, contextPreview: ?string, contextRows: list<array{key: string, valuePreview: string, valueRaw: string}>, changesRows: list<array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string}>}>}
     */
    public array $summary = [
        'status' => 'unavailable',
        'backend' => null,
        'activityCount' => 0,
        'latestDescription' => null,
        'latestAt' => null,
        'cachedAt' => null,
        'source' => null,
        'items' => [],
    ];

    public function mount(): void
    {
        $summary = app(OpsRecentActivityResolver::class)->resolve();

        $this->summary = [
            'status' => $summary->status,
            'backend' => $summary->backend,
            'activityCount' => $summary->activityCount,
            'latestDescription' => $summary->latestDescription,
            'latestAt' => $summary->latestAt,
            'cachedAt' => $summary->cachedAt,
            'source' => $summary->source,
            'items' => array_map(
                static fn (OpsRecentActivityItem $item): array => [
                    'id' => $item->id ?? sha1(sprintf('%s|%s|%s|%s', $item->description, $item->event ?? '', $item->logName ?? '', $item->loggedAt ?? '')),
                    'description' => $item->description,
                    'event' => $item->event,
                    'logName' => $item->logName,
                    'loggedAt' => $item->loggedAt,
                    'actorLabel' => $item->actorLabel,
                    'subjectLabel' => $item->subjectLabel,
                    'contextPreview' => $item->contextPreview,
                    'contextRows' => $item->contextRows,
                    'changesRows' => $item->changesRows,
                ],
                array_values($summary->items),
            ),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent audit activity')
            ->description('Privileged and operator-visible activity from the configured audit backend.')
            ->records(function (array $filters, ?string $sortColumn, ?string $sortDirection, ?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                $records = $this->activityRecords();

                $records = $this->applyFilters($records, $filters);

                if (filled($search)) {
                    $needle = mb_strtolower(trim((string) $search));

                    $records = $records->filter(static function (array $record) use ($needle): bool {
                        return str_contains(mb_strtolower($record['description']), $needle)
                            || str_contains(mb_strtolower((string) $record['event']), $needle)
                            || str_contains(mb_strtolower((string) $record['logName']), $needle)
                            || str_contains(mb_strtolower((string) $record['actorLabel']), $needle)
                            || str_contains(mb_strtolower((string) $record['subjectLabel']), $needle)
                            || str_contains(mb_strtolower((string) $record['contextPreview']), $needle)
                            || str_contains(mb_strtolower($record['loggedAt']), $needle);
                    })->values();
                }

                $sortColumn ??= 'sortLoggedAt';
                $sortDirection ??= 'desc';

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
            ->defaultSort('sortLoggedAt', 'desc')
            ->recordUrl(fn (array $record): string => AuditEntryDetailsPage::getUrl(['entry' => $record['id']], panel: (string) config('ops.panel.id', 'ops')))
            ->searchable()
            ->filters([
                Filter::make('logged_at')
                    ->label('Period')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ]),
                SelectFilter::make('actorLabel')
                    ->label('Actor')
                    ->options(fn (): array => $this->actorFilterOptions()),
                SelectFilter::make('subjectLabel')
                    ->label('Subject')
                    ->options(fn (): array => $this->subjectFilterOptions()),
                SelectFilter::make('event')
                    ->label('Event')
                    ->options(fn (): array => $this->eventFilterOptions()),
                SelectFilter::make('logName')
                    ->label('Log')
                    ->options(fn (): array => $this->logNameFilterOptions()),
                Filter::make('has_context')
                    ->label('Has context'),
            ])
            ->paginated([10, 25, 50])
            ->headerActions([
                Action::make('refreshSnapshot')
                    ->label('Refresh snapshot')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->action(fn (): mixed => $this->refreshSnapshot()),
                Action::make('exportFiltered')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn () => $this->exportFilteredActivity()),
            ])
            ->columns([
                TextColumn::make('description')
                    ->label('Entry')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('actorLabel')
                    ->label('Actor')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subjectLabel')
                    ->label('Subject')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('event')
                    ->formatStateUsing(static fn (string $state): string => $state === 'n/a' ? 'n/a' : str($state)->headline()->toString())
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('logName')
                    ->label('Log')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contextPreview')
                    ->label('Context')
                    ->formatStateUsing(static fn (?string $state): string => $state ?? 'No context')
                    ->wrap(),
                TextColumn::make('loggedAt')
                    ->label('Logged at')
                    ->sortable(),
            ])
            ->emptyStateHeading($this->emptyStateHeading())
            ->emptyStateDescription($this->emptyStateDescription());
    }

    public function getWidgetData(): array
    {
        return [
            'summary' => $this->summary,
        ];
    }

    public function heroData(): array
    {
        return [
            'eyebrow' => 'Ops audit',
            'heading' => 'Audit activity posture',
            'description' => 'Review audit backend availability, recent operator-visible activity volume, and the newest event currently available through the configured backend.',
            'metrics' => [
                [
                    'label' => 'Status',
                    'value' => str($this->summary['status'])->headline()->toString(),
                    'helperText' => 'Current audit backend posture.',
                    'display' => 'badge',
                    'tone' => $this->auditStatusTone(),
                ],
                [
                    'label' => 'Backend',
                    'value' => $this->summary['backend'] ?? 'Unavailable',
                    'helperText' => 'Configured audit backend.',
                    'display' => 'badge',
                    'tone' => 'gray',
                ],
                [
                    'label' => 'Recent entries',
                    'value' => $this->summary['activityCount'],
                    'helperText' => 'Operator-visible audit rows currently available.',
                    'display' => 'numeric',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Latest entry',
                    'value' => $this->summary['latestDescription'] === null ? 'Unavailable' : 'Available',
                    'helperText' => $this->summary['latestDescription'] ?? 'No recent audit entry available.',
                    'display' => 'badge',
                    'tone' => 'primary',
                ],
            ],
            'actions' => [],
        ];
    }

    public function auditHeroInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->heroData())
            ->components([
                Section::make('Audit activity posture')
                    ->description('Review audit backend availability, recent operator-visible activity volume, and the newest event currently available through the configured backend.')
                    ->icon(Heroicon::OutlinedShieldCheck)
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
                            ->color(fn (): string => $this->heroData()['statusTone']),
                    ])
                    ->schema([
                        TextEntry::make('activityCount')
                            ->label('Recent entries')
                            ->numeric()
                            ->icon(Heroicon::OutlinedQueueList)
                            ->iconColor('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->helperText('Number of recent audit entries available in the current snapshot.'),
                        TextEntry::make('backend')
                            ->label('Backend')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Unavailable' ? 'gray' : 'primary')
                            ->helperText('Audit source currently backing the operator-visible activity stream.'),
                        TextEntry::make('source')
                            ->label('Snapshot source')
                            ->badge()
                            ->color('gray')
                            ->helperText('Whether the current data was read freshly or from cache.'),
                        TextEntry::make('cachedAt')
                            ->label('Snapshot refreshed at')
                            ->helperText('When the current snapshot was last assembled.'),
                        TextEntry::make('latestEntryState')
                            ->label('Latest entry')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Available' ? 'success' : 'gray')
                            ->helperText('Whether the current snapshot includes a recent operator-visible audit entry.'),
                    ])
                    ->columns(3),
            ]);
    }

    public function auditDetailInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->heroData())
            ->components([
                Section::make('Audit context')
                    ->description('Additional context about the current backend snapshot and the newest available activity item.')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->schema([
                        TextEntry::make('latestAt')
                            ->label('Latest logged at')
                            ->helperText('Timestamp of the newest audit entry returned by the backend.'),
                        TextEntry::make('status')
                            ->label('Backend posture')
                            ->badge()
                            ->color(fn (): string => $this->heroData()['statusTone'])
                            ->helperText('Current availability posture of the configured audit backend.'),
                        TextEntry::make('latestDescription')
                            ->label('Latest entry')
                            ->columnSpanFull()
                            ->helperText('Most recent operator-visible audit description available right now.'),
                    ])
                    ->columns(3),
            ]);
    }

    public function refreshSnapshot(): void
    {
        $summary = app(RefreshAuditSnapshotAction::class)->run();

        $this->summary = [
            'status' => $summary->status,
            'backend' => $summary->backend,
            'activityCount' => $summary->activityCount,
            'latestDescription' => $summary->latestDescription,
            'latestAt' => $summary->latestAt,
            'cachedAt' => $summary->cachedAt,
            'source' => $summary->source,
            'items' => array_map(
                static fn (OpsRecentActivityItem $item): array => [
                    'id' => $item->id ?? sha1(sprintf('%s|%s|%s|%s', $item->description, $item->event ?? '', $item->logName ?? '', $item->loggedAt ?? '')),
                    'description' => $item->description,
                    'event' => $item->event,
                    'logName' => $item->logName,
                    'loggedAt' => $item->loggedAt,
                    'actorLabel' => $item->actorLabel,
                    'subjectLabel' => $item->subjectLabel,
                    'contextPreview' => $item->contextPreview,
                    'contextRows' => $item->contextRows,
                    'changesRows' => $item->changesRows,
                ],
                array_values($summary->items),
            ),
        ];
    }

    /**
     * @return Collection<int, array{id: string, description: string, event: string, logName: string, loggedAt: string, actorLabel: string, subjectLabel: string, contextPreview: ?string, contextRows: list<array{key: string, valuePreview: string, valueRaw: string}>, changesRows: list<array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string}>, sortLoggedAt: string}>
     */
    private function activityRecords(): Collection
    {
        return collect($this->summary['items'])
            ->map(static function (array $item): array {
                $loggedAt = $item['loggedAt'] ?? 'n/a';

                return [
                    'id' => $item['id'],
                    'description' => $item['description'],
                    'event' => $item['event'] ?? 'n/a',
                    'logName' => $item['logName'] ?? 'n/a',
                    'actorLabel' => $item['actorLabel'] ?? 'System',
                    'subjectLabel' => $item['subjectLabel'] ?? 'Unknown subject',
                    'contextPreview' => $item['contextPreview'] ?? null,
                    'contextRows' => $item['contextRows'] ?? [],
                    'changesRows' => $item['changesRows'] ?? [],
                    'loggedAt' => $loggedAt,
                    'sortLoggedAt' => $loggedAt === 'n/a' ? '' : $loggedAt,
                ];
            });
    }

    /**
     * @param  Collection<int, array{id: string, description: string, event: string, logName: string, loggedAt: string, actorLabel: string, subjectLabel: string, contextPreview: ?string, contextRows: list<array{key: string, valuePreview: string, valueRaw: string}>, changesRows: list<array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string}>, sortLoggedAt: string}>  $records
     * @param  array<string, array<string, mixed>>  $filters
     * @return Collection<int, array{id: string, description: string, event: string, logName: string, loggedAt: string, actorLabel: string, subjectLabel: string, contextPreview: ?string, contextRows: list<array{key: string, valuePreview: string, valueRaw: string}>, changesRows: list<array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string}>, sortLoggedAt: string}>
     */
    private function applyFilters(Collection $records, array $filters): Collection
    {
        $period = $filters['logged_at'] ?? [];
        $from = filled($period['from'] ?? null) ? (string) $period['from'] : null;
        $until = filled($period['until'] ?? null) ? (string) $period['until'] : null;
        $actorLabel = $filters['actorLabel']['value'] ?? null;
        $subjectLabel = $filters['subjectLabel']['value'] ?? null;
        $event = $filters['event']['value'] ?? null;
        $logName = $filters['logName']['value'] ?? null;

        if (filled($from)) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['loggedAt'] >= $from)
                ->values();
        }

        if (filled($until)) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['loggedAt'] <= $until.'T23:59:59')
                ->values();
        }

        if (filled($actorLabel)) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['actorLabel'] === $actorLabel)
                ->values();
        }

        if (filled($subjectLabel)) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['subjectLabel'] === $subjectLabel)
                ->values();
        }

        if (filled($event)) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['event'] === $event)
                ->values();
        }

        if (filled($logName)) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['logName'] === $logName)
                ->values();
        }

        if ($filters['has_context']['isActive'] ?? false) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['contextRows'] !== [] || $record['changesRows'] !== [])
                ->values();
        }

        return collect($records->all());
    }

    /**
     * @return array<string, string>
     */
    private function actorFilterOptions(): array
    {
        return $this->activityRecords()
            ->pluck('actorLabel')
            ->unique()
            ->sort()
            ->mapWithKeys(static fn (string $actorLabel): array => [$actorLabel => $actorLabel])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function subjectFilterOptions(): array
    {
        return $this->activityRecords()
            ->pluck('subjectLabel')
            ->unique()
            ->sort()
            ->mapWithKeys(static fn (string $subjectLabel): array => [$subjectLabel => $subjectLabel])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function eventFilterOptions(): array
    {
        return $this->activityRecords()
            ->pluck('event')
            ->filter(fn (string $event): bool => $event !== 'n/a')
            ->unique()
            ->sort()
            ->mapWithKeys(static fn (string $event): array => [$event => str($event)->headline()->toString()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function logNameFilterOptions(): array
    {
        return $this->activityRecords()
            ->pluck('logName')
            ->filter(fn (string $logName): bool => $logName !== 'n/a')
            ->unique()
            ->sort()
            ->mapWithKeys(static fn (string $logName): array => [$logName => $logName])
            ->all();
    }

    private function exportFilteredActivity(): mixed
    {
        $records = $this->applyFilters($this->activityRecords(), $this->tableFilters);

        if (filled($this->tableSearch)) {
            $needle = mb_strtolower(trim((string) $this->tableSearch));

            $records = $records->filter(static function (array $record) use ($needle): bool {
                return str_contains(mb_strtolower($record['description']), $needle)
                    || str_contains(mb_strtolower((string) $record['event']), $needle)
                    || str_contains(mb_strtolower((string) $record['logName']), $needle)
                    || str_contains(mb_strtolower((string) $record['actorLabel']), $needle)
                    || str_contains(mb_strtolower((string) $record['subjectLabel']), $needle)
                    || str_contains(mb_strtolower((string) $record['contextPreview']), $needle)
                    || str_contains(mb_strtolower($record['loggedAt']), $needle);
            })->values();
        }

        $records = $records->sortBy($this->tableSortColumn ?? 'sortLoggedAt', SORT_NATURAL, ($this->tableSortDirection ?? 'desc') === 'desc')->values();

        $lines = collect([
            ['id', 'loggedAt', 'event', 'logName', 'actorLabel', 'subjectLabel', 'description', 'contextPreview'],
            ...$records->map(static fn (array $record): array => [
                $record['id'],
                $record['loggedAt'],
                $record['event'],
                $record['logName'],
                $record['actorLabel'],
                $record['subjectLabel'],
                $record['description'],
                $record['contextPreview'] ?? '',
            ])->all(),
        ])->map(static function (array $row): string {
            return implode(',', array_map(static fn (mixed $value): string => '"'.str_replace('"', '""', (string) $value).'"', $row));
        })->implode("\n");

        return response()->streamDownload(static function () use ($lines): void {
            echo $lines;
        }, 'audit-trail.csv', ['Content-Type' => 'text/csv']);
    }

    private function emptyStateHeading(): string
    {
        return match ($this->summary['status']) {
            'unavailable' => 'No supported audit backend is currently installed.',
            'degraded' => 'Recent audit activity is currently unavailable.',
            default => 'No recent audit entries are currently available.',
        };
    }

    private function emptyStateDescription(): string
    {
        return match ($this->summary['status']) {
            'unavailable' => 'Install a supported audit backend to surface recent operator-visible activity.',
            'degraded' => 'The audit backend is present, but recent activity could not be read.',
            default => 'No recent audit entries are currently available.',
        };
    }

    private function auditStatusTone(): string
    {
        return match ($this->summary['status']) {
            'available' => 'success',
            'empty' => 'warning',
            'degraded' => 'danger',
            default => 'gray',
        };
    }
}
