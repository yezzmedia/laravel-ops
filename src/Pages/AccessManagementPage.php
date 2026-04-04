<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;
use YezzMedia\Ops\Support\OpsAccessBridge;
use YezzMedia\Ops\Support\OpsGuardResolver;

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

    public function heroData(): array
    {
        return [
            'eyebrow' => 'Access operations',
            'heading' => 'Role management',
            'description' => 'Review persisted roles, operator coverage, and the current super-admin posture before changing assignments.',
            'roleCount' => count($this->overview['roles']),
            'assignmentCount' => collect($this->overview['roles'])->sum('assignmentCount'),
            'superAdminRole' => $this->overview['superAdmin']['roleName'] ?? 'Disabled',
            'status' => $this->status(),
            'metrics' => [
                [
                    'label' => 'Persisted roles',
                    'value' => count($this->overview['roles']),
                    'helperText' => 'Roles currently stored in the access runtime.',
                    'display' => 'numeric',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Assigned operators',
                    'value' => collect($this->overview['roles'])->sum('assignmentCount'),
                    'helperText' => 'Total persisted role assignments across operators.',
                    'display' => 'numeric',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Super-admin role',
                    'value' => $this->overview['superAdmin']['roleName'] ?? 'Disabled',
                    'helperText' => 'Current elevated-role posture configured for access management.',
                    'display' => 'badge',
                    'tone' => $this->overview['superAdmin']['enabled'] ? 'success' : 'gray',
                ],
                [
                    'label' => 'Management status',
                    'value' => $this->status(),
                    'helperText' => 'Current runtime state for access management workflows.',
                    'display' => 'badge',
                    'tone' => $this->statusTone($this->status()),
                ],
            ],
            'actions' => [],
        ];
    }

    public function accessManagementHeroInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->heroData())
            ->components([
                Section::make('Role management')
                    ->description('Review persisted roles, operator coverage, and the current super-admin posture before changing assignments.')
                    ->icon(Heroicon::OutlinedUsers)
                    ->schema([
                        TextEntry::make('roleCount')
                            ->label('Persisted roles')
                            ->numeric()
                            ->badge(),
                        TextEntry::make('assignmentCount')
                            ->label('Assigned operators')
                            ->numeric()
                            ->badge(),
                        TextEntry::make('superAdminRole')
                            ->label('Super-admin role')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Disabled' ? 'gray' : 'success'),
                        TextEntry::make('status')
                            ->label('Management status')
                            ->badge()
                            ->color(fn (string $state): string => $this->statusTone($state)),
                    ])
                    ->columns(4),
            ]);
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
            $message = app(OpsAccessBridge::class)->syncSuggestedRoles($this->actor());
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
            ->recordUrl(fn (array $record): string => RoleDetailsPage::getUrl(['role' => $record['name']], panel: (string) config('ops.panel.id', 'ops')))
            ->records(function (?string $sortColumn, ?string $sortDirection, ?string $search, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator {
                $records = $this->roleRecords();

                if (filled($search)) {
                    $needle = mb_strtolower(trim((string) $search));

                    $records = $records->filter(static function (array $record) use ($needle): bool {
                        return str_contains(mb_strtolower($record['name']), $needle)
                            || str_contains(mb_strtolower($record['permissionNamesLabel']), $needle);
                    })->values();
                }

                $records = $this->applyRoleFilters($records, $filters);

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
            ->filters([
                Filter::make('super_admin')
                    ->label('Super-admin role')
                    ->toggle(),
                Filter::make('has_assignments')
                    ->label('Has assignments')
                    ->toggle(),
                Filter::make('has_permissions')
                    ->label('Has permissions')
                    ->toggle(),
            ])
            ->columns([
                TextColumn::make('name')
                    ->label('Role')
                    ->badge()
                    ->color('gray')
                    ->description(fn (array $record): string => $record['permissionNamesLabel'])
                    ->wrap()
                    ->lineClamp(2)
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
            ])
            ->emptyStateHeading('No persisted roles are currently available for access management.');
    }

    private function refreshOverview(): void
    {
        $this->overview = app(OpsAccessBridge::class)->managementOverview();
    }

    private function status(): string
    {
        if ($this->overview['error'] !== null) {
            return 'Warning';
        }

        if ($this->overview['superAdmin']['enabled']) {
            return 'Protected';
        }

        return 'Standard';
    }

    private function statusTone(string $state): string
    {
        return match ($state) {
            'Protected' => 'success',
            'Warning' => 'warning',
            default => 'gray',
        };
    }

    /**
     * @param  Collection<int, array{name: string, permissionCount: int, assignmentCount: int, permissionNames: list<string>}>  $records
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{name: string, permissionCount: int, assignmentCount: int, permissionNames: list<string>, permissionNamesLabel: string, isSuperAdminRole: bool, hasAssignments: bool, hasPermissions: bool}>
     */
    private function applyRoleFilters(Collection $records, array $filters): Collection
    {
        $records = $records
            ->when(array_key_exists('super_admin', $filters), function (Collection $roles) use ($filters): Collection {
                if (($filters['super_admin']['isActive'] ?? false) === true) {
                    return $roles->filter(static fn (array $role): bool => $role['name'] === (string) config('access.super_admin.role_name', 'super-admin'));
                }

                if (($filters['super_admin']['isActive'] ?? false) === false) {
                    return $roles->filter(static fn (array $role): bool => $role['name'] !== (string) config('access.super_admin.role_name', 'super-admin'));
                }

                return $roles;
            })
            ->when(array_key_exists('has_assignments', $filters), function (Collection $roles) use ($filters): Collection {
                if (($filters['has_assignments']['isActive'] ?? false) === true) {
                    return $roles->filter(static fn (array $role): bool => $role['assignmentCount'] > 0);
                }

                if (($filters['has_assignments']['isActive'] ?? false) === false) {
                    return $roles->filter(static fn (array $role): bool => $role['assignmentCount'] === 0);
                }

                return $roles;
            })
            ->when(array_key_exists('has_permissions', $filters), function (Collection $roles) use ($filters): Collection {
                if (($filters['has_permissions']['isActive'] ?? false) === true) {
                    return $roles->filter(static fn (array $role): bool => $role['permissionCount'] > 0);
                }

                if (($filters['has_permissions']['isActive'] ?? false) === false) {
                    return $roles->filter(static fn (array $role): bool => $role['permissionCount'] === 0);
                }

                return $roles;
            });

        return $records->map(static function (array $role): array {
            $permissionNames = $role['permissionNames'];

            return [
                ...$role,
                'permissionNamesLabel' => $permissionNames === [] ? 'n/a' : implode(', ', $permissionNames),
                'isSuperAdminRole' => $role['name'] === (string) config('access.super_admin.role_name', 'super-admin'),
                'hasAssignments' => $role['assignmentCount'] > 0,
                'hasPermissions' => $role['permissionCount'] > 0,
            ];
        });
    }

    private function actor(): ?Authenticatable
    {
        $guard = app(OpsGuardResolver::class)->resolve()['guard'];
        $user = Auth::guard($guard)->user();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * @return Collection<int, array{name: string, permissionCount: int, assignmentCount: int, permissionNames: list<string>, permissionNamesLabel: string, isSuperAdminRole: bool, hasAssignments: bool, hasPermissions: bool}>
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
                    'isSuperAdminRole' => $role['name'] === (string) config('access.super_admin.role_name', 'super-admin'),
                    'hasAssignments' => $role['assignmentCount'] > 0,
                    'hasPermissions' => $role['permissionCount'] > 0,
                ];
            });
    }
}
