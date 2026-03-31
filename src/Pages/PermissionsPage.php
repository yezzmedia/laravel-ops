<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;
use UnitEnum;
use YezzMedia\Ops\Support\OpsAccessBridge;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;
use YezzMedia\Ops\Widgets\PermissionOverviewWidget;
use YezzMedia\Ops\Widgets\PermissionRuntimeStatusWidget;
use YezzMedia\Ops\Widgets\RoleRelationshipsWidget;

/**
 * Provides read-oriented access visibility and a tightly scoped sync entry point.
 */
final class PermissionsPage extends OpsPage implements HasTable
{
    use InteractsWithTable;

    protected static string $opsSurface = 'permissions';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?string $navigationLabel = 'Permissions';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'access/permissions';

    protected string $view = 'ops::pages.permissions-page';

    /**
     * @var array{
     *     installed: bool,
     *     available: bool,
     *     error: ?string,
     *     store: array{configPublished: bool, migrationsPublished: bool, pendingMigrations: bool, ready: bool},
     *     permissions: list<array{name: string, package: string, label: string, synced: bool, roleHints: list<string>, assignedRoles: list<string>}>,
     *     roles: list<array{name: string, permissionNames: list<string>}>
     * }
     */
    public array $overview = [
        'installed' => false,
        'available' => false,
        'error' => null,
        'store' => [
            'configPublished' => false,
            'migrationsPublished' => false,
            'pendingMigrations' => false,
            'ready' => false,
        ],
        'permissions' => [],
        'roles' => [],
    ];

    public function mount(): void
    {
        $this->refreshOverview();
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('syncPermissions')
                ->label('Sync permissions')
                ->icon(Heroicon::OutlinedArrowPath)
                ->authorize(fn (): bool => app(OpsAuthorizationResolver::class)->canAccessSurface('access_management'))
                ->visible(fn (): bool => app(OpsAuthorizationResolver::class)->canAccessSurface('access_management'))
                ->action(function (): void {
                    $this->syncPermissions();
                }),
        ];
    }

    public function syncPermissions(): void
    {
        try {
            $message = app(OpsAccessBridge::class)->syncPermissions();
            $this->refreshOverview();

            Notification::make()
                ->title('Permissions synchronized')
                ->body($message)
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Permission sync failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Declared permissions')
            ->description('Read-oriented visibility for foundation-declared permissions and their package ownership.')
            ->records(function (?string $sortColumn, ?string $sortDirection, ?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                $records = $this->permissionRecords();

                if (filled($search)) {
                    $needle = mb_strtolower(trim((string) $search));

                    $records = $records->filter(static function (array $record) use ($needle): bool {
                        return str_contains(mb_strtolower($record['name']), $needle)
                            || str_contains(mb_strtolower($record['label']), $needle)
                            || str_contains(mb_strtolower($record['package']), $needle)
                            || str_contains(mb_strtolower($record['roleHintsLabel']), $needle)
                            || str_contains(mb_strtolower($record['assignedRolesLabel']), $needle);
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
            ->searchable()
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('name')
                    ->label('Permission')
                    ->description(fn (array $record): string => $record['label'])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('package')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('synced')
                    ->label('Synced')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('roleHintsLabel')
                    ->label('Role hints')
                    ->wrap(),
                TextColumn::make('assignedRolesLabel')
                    ->label('Assigned roles')
                    ->wrap(),
            ])
            ->emptyStateHeading('No declared permissions are currently available.');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PermissionOverviewWidget::class,
            PermissionRuntimeStatusWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RoleRelationshipsWidget::class,
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
            'overview' => $this->overview,
        ];
    }

    private function refreshOverview(): void
    {
        $this->overview = app(OpsAccessBridge::class)->permissionOverview();
    }

    /**
     * @return Collection<int, array{name: string, package: string, label: string, synced: bool, roleHints: list<string>, assignedRoles: list<string>, roleHintsLabel: string, assignedRolesLabel: string}>
     */
    private function permissionRecords(): Collection
    {
        return collect($this->overview['permissions'])
            ->map(static function (array $permission): array {
                return [
                    ...$permission,
                    'roleHintsLabel' => $permission['roleHints'] === [] ? 'n/a' : implode(', ', $permission['roleHints']),
                    'assignedRolesLabel' => $permission['assignedRoles'] === [] ? 'n/a' : implode(', ', $permission['assignedRoles']),
                ];
            });
    }
}
