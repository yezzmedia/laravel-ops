<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use YezzMedia\Ops\Support\OpsPackageOverviewResolver;
use YezzMedia\Ops\Widgets\InstalledPackagesWidget;

/**
 * Curates package-level operator visibility for the installed platform.
 */
final class PackagesPage extends OpsPage implements HasTable
{
    use InteractsWithTable;

    protected static string $opsSurface = 'packages';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Packages';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'packages';

    protected string $view = 'ops::pages.packages-page';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Platform packages')
            ->description('Curated package readiness, ownership, and operator-facing entry points.')
            ->records(function (array $filters, ?string $sortColumn, ?string $sortDirection, ?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                $records = $this->packageRecords();

                $records = $this->applyFilters($records, $filters);

                if (filled($search)) {
                    $needle = mb_strtolower(trim((string) $search));

                    $records = $records->filter(static function (array $record) use ($needle): bool {
                        return str_contains(mb_strtolower($record['name']), $needle)
                            || str_contains(mb_strtolower($record['vendor']), $needle)
                            || str_contains(mb_strtolower($record['description']), $needle)
                            || str_contains(mb_strtolower($record['entryPointsLabel']), $needle);
                    })->values();
                }

                $sortColumn ??= 'name';
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
            ->defaultSort('name')
            ->recordUrl(fn (array $record): string => PackageDetailsPage::getUrl(['package' => $record['name']]))
            ->searchable()
            ->filters([
                SelectFilter::make('enabled')
                    ->label('Enabled')
                    ->options([
                        'enabled' => 'Enabled',
                        'disabled' => 'Disabled',
                    ]),
                SelectFilter::make('posture')
                    ->label('Posture')
                    ->options([
                        'healthy' => 'Healthy',
                        'limited' => 'Limited',
                        'disabled' => 'Disabled',
                    ]),
                Filter::make('has_features')
                    ->label('Has features'),
                Filter::make('has_permissions')
                    ->label('Has permissions'),
                Filter::make('has_ops_modules')
                    ->label('Has ops modules'),
                Filter::make('has_entry_points')
                    ->label('Has entry points'),
            ])
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('name')
                    ->label('Package')
                    ->description(fn (array $record): string => $record['description'])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('postureSort')
                    ->label('Posture')
                    ->state(fn (array $record): string => $record['postureLabel'])
                    ->badge()
                    ->color(fn (array $record): string => $record['postureTone'])
                    ->sortable(),
                IconColumn::make('enabled')
                    ->label('Enabled')
                    ->boolean()
                    ->tooltip(fn (bool $state): string => $state ? 'Participates in capability aggregation.' : 'Visible in inventory but excluded from capability aggregation.')
                    ->sortable(),
                TextColumn::make('featureCount')
                    ->label('Features')
                    ->badge()
                    ->sortable(),
                TextColumn::make('permissionCount')
                    ->label('Permissions')
                    ->badge()
                    ->sortable(),
                TextColumn::make('opsModuleCount')
                    ->label('Ops modules')
                    ->badge()
                    ->sortable(),
                TextColumn::make('priority')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'n/a' : (string) $state)
                    ->sortable(),
                TextColumn::make('entryPointsLabel')
                    ->label('Entry points')
                    ->wrap(),
            ])
            ->emptyStateHeading('No platform packages are currently registered.');
    }

    /**
     * @param  Collection<int, array{name: string, vendor: string, description: string, packageClass: string, enabled: bool, priority: ?int, posture: string, postureLabel: string, postureTone: string, postureSort: int, featureCount: int, permissionCount: int, opsModuleCount: int, entryPoints: list<string>, entryPointsLabel: string}>  $records
     * @param  array<string, array<string, mixed>>  $filters
     * @return Collection<int, array{name: string, vendor: string, description: string, packageClass: string, enabled: bool, priority: ?int, posture: string, postureLabel: string, postureTone: string, postureSort: int, featureCount: int, permissionCount: int, opsModuleCount: int, entryPoints: list<string>, entryPointsLabel: string}>
     */
    private function applyFilters(Collection $records, array $filters): Collection
    {
        $enabled = $filters['enabled']['value'] ?? null;
        $posture = $filters['posture']['value'] ?? null;

        if (filled($enabled)) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['enabled'] === ($enabled === 'enabled'))
                ->values();
        }

        if (filled($posture)) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['posture'] === $posture)
                ->values();
        }

        if ($filters['has_features']['isActive'] ?? false) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['featureCount'] > 0)
                ->values();
        }

        if ($filters['has_permissions']['isActive'] ?? false) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['permissionCount'] > 0)
                ->values();
        }

        if ($filters['has_ops_modules']['isActive'] ?? false) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['opsModuleCount'] > 0)
                ->values();
        }

        if ($filters['has_entry_points']['isActive'] ?? false) {
            $records = $records
                ->filter(static fn (array $record): bool => $record['entryPoints'] !== [])
                ->values();
        }

        return collect($records->all());
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InstalledPackagesWidget::class,
        ];
    }

    /**
     * @return Collection<int, array{name: string, vendor: string, description: string, packageClass: string, enabled: bool, priority: ?int, posture: string, postureLabel: string, postureTone: string, postureSort: int, featureCount: int, permissionCount: int, opsModuleCount: int, entryPoints: list<string>, entryPointsLabel: string}>
     */
    private function packageRecords(): Collection
    {
        return collect(app(OpsPackageOverviewResolver::class)->resolve())
            ->map(static function (array $record): array {
                $entryPoints = $record['entryPoints'];

                return [
                    ...$record,
                    'entryPointsLabel' => $entryPoints === []
                        ? 'No package pages'
                        : implode(', ', $entryPoints),
                ];
            })
            ->sortBy([
                ['postureSort', 'desc'],
                ['name', 'asc'],
            ])
            ->values();
    }
}
