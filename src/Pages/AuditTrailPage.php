<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use YezzMedia\Ops\Data\OpsRecentActivityItem;
use YezzMedia\Ops\Support\OpsRecentActivityResolver;
use YezzMedia\Ops\Widgets\AuditStatusWidget;
use YezzMedia\Ops\Widgets\RecentActivityWidget;

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

    public function mount(): void
    {
        $summary = app(OpsRecentActivityResolver::class)->resolve();

        $this->summary = [
            'status' => $summary->status,
            'backend' => $summary->backend,
            'activityCount' => $summary->activityCount,
            'latestDescription' => $summary->latestDescription,
            'latestAt' => $summary->latestAt,
            'items' => array_map(
                static fn (OpsRecentActivityItem $item): array => [
                    'description' => $item->description,
                    'event' => $item->event,
                    'logName' => $item->logName,
                    'loggedAt' => $item->loggedAt,
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
            ->records(function (?string $sortColumn, ?string $sortDirection, ?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                $records = $this->activityRecords();

                if (filled($search)) {
                    $needle = mb_strtolower(trim((string) $search));

                    $records = $records->filter(static function (array $record) use ($needle): bool {
                        return str_contains(mb_strtolower($record['description']), $needle)
                            || str_contains(mb_strtolower($record['event']), $needle)
                            || str_contains(mb_strtolower($record['logName']), $needle)
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
            ->searchable()
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('description')
                    ->label('Entry')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('event')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('logName')
                    ->label('Log')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('loggedAt')
                    ->label('Logged at')
                    ->sortable(),
            ])
            ->emptyStateHeading($this->emptyStateHeading())
            ->emptyStateDescription($this->emptyStateDescription());
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RecentActivityWidget::class,
            AuditStatusWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 1,
            'xl' => 2,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'summary' => $this->summary,
        ];
    }

    /**
     * @return Collection<int, array{description: string, event: string, logName: string, loggedAt: string, sortLoggedAt: string}>
     */
    private function activityRecords(): Collection
    {
        return collect($this->summary['items'])
            ->map(static function (array $item): array {
                $loggedAt = $item['loggedAt'] ?? 'n/a';

                return [
                    'description' => $item['description'],
                    'event' => $item['event'] ?? 'n/a',
                    'logName' => $item['logName'] ?? 'n/a',
                    'loggedAt' => $loggedAt,
                    'sortLoggedAt' => $loggedAt === 'n/a' ? '' : $loggedAt,
                ];
            });
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
}
