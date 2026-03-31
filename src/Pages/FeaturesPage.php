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
use YezzMedia\Ops\Support\OpsFeatureOverviewResolver;
use YezzMedia\Ops\Widgets\RegisteredFeaturesWidget;

/**
 * Surfaces a feature-oriented platform overview for operators.
 */
final class FeaturesPage extends OpsPage implements HasTable
{
    use InteractsWithTable;

    protected static string $opsSurface = 'features';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Features';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'features';

    protected string $view = 'ops::pages.features-page';

    /**
     * @var list<array{name: string, label: string, package: string, description: ?string, packageDescription: ?string, entryPoints: list<string>}>
     */
    public array $features = [];

    public function mount(): void
    {
        $this->features = app(OpsFeatureOverviewResolver::class)->resolve();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Platform features')
            ->description('Registered platform features with package ownership and related operator entry points.')
            ->records(function (?string $sortColumn, ?string $sortDirection, ?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                $records = $this->featureRecords();

                if (filled($search)) {
                    $needle = mb_strtolower(trim((string) $search));

                    $records = $records->filter(static function (array $record) use ($needle): bool {
                        return str_contains(mb_strtolower($record['name']), $needle)
                            || str_contains(mb_strtolower($record['label']), $needle)
                            || str_contains(mb_strtolower($record['package']), $needle)
                            || str_contains(mb_strtolower($record['description']), $needle)
                            || str_contains(mb_strtolower($record['packageDescription']), $needle)
                            || str_contains(mb_strtolower($record['entryPointsLabel']), $needle);
                    })->values();
                }

                $sortColumn ??= 'sortKey';
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
            ->defaultSort('sortKey')
            ->searchable()
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('label')
                    ->label('Feature')
                    ->description(fn (array $record): string => $record['name'])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('package')
                    ->badge()
                    ->color('gray')
                    ->description(fn (array $record): ?string => $record['packageDescription'])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->formatStateUsing(static fn (string $state): string => $state)
                    ->wrap(),
                TextColumn::make('entryPointsLabel')
                    ->label('Related entry points')
                    ->wrap(),
            ])
            ->emptyStateHeading('No platform features are currently registered.');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RegisteredFeaturesWidget::class,
        ];
    }

    /**
     * @return Collection<int, array{name: string, label: string, package: string, description: string, packageDescription: string, entryPoints: list<string>, entryPointsLabel: string, sortKey: string}>
     */
    private function featureRecords(): Collection
    {
        return collect($this->features)
            ->map(static function (array $record): array {
                $entryPoints = $record['entryPoints'];

                return [
                    ...$record,
                    'description' => $record['description'] ?? 'No feature description is currently registered.',
                    'packageDescription' => $record['packageDescription'] ?? '',
                    'entryPointsLabel' => $entryPoints === []
                        ? 'No package pages'
                        : implode(', ', $entryPoints),
                    'sortKey' => sprintf('%s::%s', $record['package'], $record['name']),
                ];
            });
    }
}
