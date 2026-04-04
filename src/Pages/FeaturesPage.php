<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Support\Str;
use YezzMedia\Ops\Support\OpsFeatureOverviewResolver;

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
     * @var list<array{name: string, label: string, package: string, description: ?string, packageDescription: ?string, entryPoints: list<string>, entryPointsLabel: string, hasEntryPoints: bool, sortKey: string}>
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
            ->records(function (?string $sortColumn, ?string $sortDirection, ?string $search, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator {
                $records = $this->featureRecords();

                $records = $this->applyFilters($records, $filters);

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
            ->filters([
                SelectFilter::make('package')
                    ->label('Package')
                    ->options(fn (): array => $this->packageFilterOptions())
                    ->searchable(),
                Filter::make('has_entry_points')
                    ->label('Has entry points'),
            ])
            ->paginated([10, 25, 50])
            ->headerActions([
                Action::make('exportFiltered')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn () => $this->exportFilteredFeatures()),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Feature')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('package')
                    ->label('Package')
                    ->state(static fn (array $record): string => Str::of($record['package'])->replaceFirst('/', "\n")->toString())
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('description')
                    ->formatStateUsing(static fn (string $state): string => $state)
                    ->wrap(),
                TextColumn::make('entryPointsLabel')
                    ->label('Related entry points')
                    ->wrap(),
            ])
            ->emptyStateHeading('No platform features are currently registered.');
    }

    public function heroData(): array
    {
        return [
            'eyebrow' => 'Ops visibility',
            'heading' => 'Platform feature inventory',
            'description' => 'Review the approved platform features, the packages that contribute them, and which capabilities already expose operator entry points.',
            'metrics' => [
                [
                    'label' => 'Registered features',
                    'value' => $this->featureCount(),
                    'helperText' => 'Approved capabilities currently visible through the platform registries.',
                    'display' => 'numeric',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Packages contributing',
                    'value' => $this->featurePackageCount(),
                    'helperText' => 'Packages that currently register at least one platform feature.',
                    'display' => 'numeric',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'With entry points',
                    'value' => $this->featuresWithEntryPointsCount(),
                    'helperText' => 'Features whose owning package already exposes related operator entry points.',
                    'display' => 'numeric',
                    'tone' => 'primary',
                ],
            ],
            'actions' => [],
        ];
    }

    public function featuresHeroInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->heroData())
            ->components([
                Section::make('Platform feature inventory')
                    ->description('Review the approved platform features, the packages that contribute them, and which capabilities already expose operator entry points.')
                    ->icon(Heroicon::OutlinedViewColumns)
                    ->iconColor('primary')
                    ->afterHeader([
                        TextEntry::make('eyebrow')
                            ->hiddenLabel()
                            ->badge()
                            ->icon(Heroicon::OutlinedSparkles)
                            ->color('primary'),
                    ])
                    ->schema([
                        TextEntry::make('featureCount')
                            ->label('Registered features')
                            ->numeric()
                            ->icon(Heroicon::OutlinedViewColumns)
                            ->iconColor('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->helperText('Approved capabilities currently visible through the platform registries.'),
                        TextEntry::make('featurePackageCount')
                            ->label('Packages contributing')
                            ->numeric()
                            ->icon(Heroicon::OutlinedCube)
                            ->iconColor('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->helperText('Packages that currently register at least one platform feature.'),
                        TextEntry::make('featuresWithEntryPointsCount')
                            ->label('With entry points')
                            ->numeric()
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->iconColor('primary')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->helperText('Features whose owning package already exposes related operator entry points.'),
                    ])
                    ->columns(3),
            ]);
    }

    /**
     * @return Collection<int, array{name: string, label: string, package: string, description: string, packageDescription: string, entryPoints: list<string>, entryPointsLabel: string, hasEntryPoints: bool, sortKey: string}>
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
                    'hasEntryPoints' => $entryPoints !== [],
                    'sortKey' => sprintf('%s::%s', $record['package'], $record['name']),
                ];
            });
    }

    /**
     * @param  Collection<int, array{name: string, label: string, package: string, description: string, packageDescription: string, entryPoints: list<string>, entryPointsLabel: string, hasEntryPoints: bool, sortKey: string}>  $records
     * @param  array<string, array<string, mixed>>  $filters
     * @return Collection<int, array{name: string, label: string, package: string, description: string, packageDescription: string, entryPoints: list<string>, entryPointsLabel: string, hasEntryPoints: bool, sortKey: string}>
     */
    private function applyFilters(Collection $records, array $filters): Collection
    {
        $package = $filters['package']['value'] ?? null;

        if (filled($package)) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['package'] === $package)
                ->values();
        }

        if ($filters['has_entry_points']['isActive'] ?? false) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['hasEntryPoints'])
                ->values();
        }

        return collect($records->all());
    }

    /**
     * @return array<string, string>
     */
    private function packageFilterOptions(): array
    {
        return $this->featureRecords()
            ->pluck('package')
            ->unique()
            ->sort()
            ->mapWithKeys(static fn (string $package): array => [$package => $package])
            ->all();
    }

    private function exportFilteredFeatures(): mixed
    {
        $records = $this->applyFilters($this->featureRecords(), $this->tableFilters);

        if (filled($this->tableSearch)) {
            $needle = mb_strtolower(trim((string) $this->tableSearch));

            $records = $records->filter(static function (array $record) use ($needle): bool {
                return str_contains(mb_strtolower($record['name']), $needle)
                    || str_contains(mb_strtolower($record['label']), $needle)
                    || str_contains(mb_strtolower($record['package']), $needle)
                    || str_contains(mb_strtolower($record['description']), $needle)
                    || str_contains(mb_strtolower($record['packageDescription']), $needle)
                    || str_contains(mb_strtolower($record['entryPointsLabel']), $needle);
            })->values();
        }

        $records = $records
            ->sortBy($this->tableSortColumn ?? 'sortKey', SORT_NATURAL, ($this->tableSortDirection ?? 'asc') === 'desc')
            ->values();

        $csv = collect([
            ['name', 'label', 'package', 'description', 'packageDescription', 'entryPointsLabel'],
            ...$records->map(static fn (array $record): array => [
                $record['name'],
                $record['label'],
                $record['package'],
                $record['description'],
                $record['packageDescription'],
                $record['entryPointsLabel'],
            ])->all(),
        ])->map(static function (array $row): string {
            return implode(',', array_map(static fn (mixed $value): string => '"'.str_replace('"', '""', (string) $value).'"', $row));
        })->implode("\n");

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, 'platform-features.csv', ['Content-Type' => 'text/csv']);
    }

    private function featureCount(): int
    {
        return count($this->features);
    }

    private function featurePackageCount(): int
    {
        return collect($this->features)
            ->pluck('package')
            ->unique()
            ->count();
    }

    private function featuresWithEntryPointsCount(): int
    {
        return collect($this->features)
            ->filter(static fn (array $feature): bool => $feature['entryPoints'] !== [])
            ->count();
    }
}
