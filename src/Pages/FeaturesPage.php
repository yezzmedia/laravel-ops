<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Infolists\Components\TextEntry;
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

    public function featureHeroSummary(): array
    {
        return [
            'eyebrow' => 'Ops visibility',
            'heading' => 'Platform feature inventory',
            'description' => 'Review the approved platform features, the packages that contribute them, and which capabilities already expose operator entry points.',
            'featureCount' => $this->featureCount(),
            'featurePackageCount' => $this->featurePackageCount(),
            'featuresWithEntryPointsCount' => $this->featuresWithEntryPointsCount(),
        ];
    }

    public function featuresHeroInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->featureHeroSummary())
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
