<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;
use YezzMedia\Ops\Support\OpsAccessBridge;
use YezzMedia\Ops\Support\OpsGuardResolver;
use YezzMedia\Ops\Widgets\AccessManagementOverviewWidget;
use YezzMedia\Ops\Widgets\AccessManagementStatusWidget;

/**
 * Provides write-capable access management entry points backed by access-owned services.
 */
final class AccessManagementPage extends OpsPage implements HasTable
{
    use InteractsWithTable;

    protected static string $opsSurface = 'access_management';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?string $navigationLabel = 'Manage access';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'access/manage';

    protected string $view = 'ops::pages.access-management-page';

    /**
     * @var array{
     *     installed: bool,
     *     available: bool,
     *     error: ?string,
     *     superAdmin: array{enabled: bool, roleName: ?string, minimumOperators: int, operatorCount: int},
     *     roles: list<array{name: string, permissionCount: int, assignmentCount: int, permissionNames: list<string>}>
     * }
     */
    public array $overview = [
        'installed' => false,
        'available' => false,
        'error' => null,
        'superAdmin' => [
            'enabled' => false,
            'roleName' => null,
            'minimumOperators' => 2,
            'operatorCount' => 0,
        ],
        'roles' => [],
    ];

    public function mount(): void
    {
        $this->refreshOverview();
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('syncSuggestedRoles')
                ->label('Sync hinted roles')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    $this->syncSuggestedRoles();
                }),
            Action::make('assignRole')
                ->label('Assign role')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->schema([
                    TextInput::make('user_id')
                        ->label('User ID')
                        ->numeric()
                        ->required(),
                    Select::make('role_name')
                        ->label('Role')
                        ->options(fn (): array => $this->roleOptions())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->assignRole($data);
                }),
            Action::make('removeRole')
                ->label('Remove role')
                ->icon(Heroicon::OutlinedMinusCircle)
                ->requiresConfirmation()
                ->schema([
                    TextInput::make('user_id')
                        ->label('User ID')
                        ->numeric()
                        ->required(),
                    Select::make('role_name')
                        ->label('Role')
                        ->options(fn (): array => $this->roleOptions())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->removeRole($data);
                }),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function roleOptions(): array
    {
        try {
            return app(OpsAccessBridge::class)->roleOptions();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assignRole(array $data): void
    {
        try {
            app(OpsAccessBridge::class)->assignRole($data['user_id'], $data['role_name'], $this->actor());
            $this->refreshOverview();

            Notification::make()
                ->title('Role assigned')
                ->body(sprintf('Assigned [%s] to user [%s].', $data['role_name'], $data['user_id']))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Role assignment failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function removeRole(array $data): void
    {
        try {
            app(OpsAccessBridge::class)->removeRole($data['user_id'], $data['role_name'], $this->actor());
            $this->refreshOverview();

            Notification::make()
                ->title('Role removed')
                ->body(sprintf('Removed [%s] from user [%s].', $data['role_name'], $data['user_id']))
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Role removal failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncSuggestedRoles(): void
    {
        try {
            $message = app(OpsAccessBridge::class)->syncSuggestedRoles();
            $this->refreshOverview();

            Notification::make()
                ->title('Hinted roles synchronized')
                ->body($message)
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Role synchronization failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Persisted roles')
            ->description('Role composition, permission breadth, and current assignment counts.')
            ->records(function (?string $sortColumn, ?string $sortDirection, ?string $search, int $page, int $recordsPerPage): LengthAwarePaginator {
                $records = $this->roleRecords();

                if (filled($search)) {
                    $needle = mb_strtolower(trim((string) $search));

                    $records = $records->filter(static function (array $record) use ($needle): bool {
                        return str_contains(mb_strtolower($record['name']), $needle)
                            || str_contains(mb_strtolower($record['permissionNamesLabel']), $needle);
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
                    ->label('Role')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('permissionCount')
                    ->label('Permissions')
                    ->badge()
                    ->sortable(),
                TextColumn::make('assignmentCount')
                    ->label('Assignments')
                    ->badge()
                    ->sortable(),
                TextColumn::make('permissionNamesLabel')
                    ->label('Permission names')
                    ->wrap(),
            ])
            ->emptyStateHeading('No persisted roles are currently available for access management.');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AccessManagementOverviewWidget::class,
            AccessManagementStatusWidget::class,
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
        $this->overview = app(OpsAccessBridge::class)->managementOverview();
    }

    private function actor(): ?Authenticatable
    {
        $guard = app(OpsGuardResolver::class)->resolve()['guard'];
        $user = Auth::guard($guard)->user();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * @return Collection<int, array{name: string, permissionCount: int, assignmentCount: int, permissionNames: list<string>, permissionNamesLabel: string}>
     */
    private function roleRecords(): Collection
    {
        return collect($this->overview['roles'])
            ->map(static function (array $role): array {
                return [
                    ...$role,
                    'permissionNamesLabel' => $role['permissionNames'] === []
                        ? 'n/a'
                        : implode(', ', $role['permissionNames']),
                ];
            });
    }
}
