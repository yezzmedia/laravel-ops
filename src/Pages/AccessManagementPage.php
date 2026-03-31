<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;
use YezzMedia\Ops\Support\OpsAccessBridge;
use YezzMedia\Ops\Support\OpsGuardResolver;

/**
 * Provides write-capable access management entry points backed by access-owned services.
 */
final class AccessManagementPage extends OpsPage
{
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
}
