<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;
use YezzMedia\Ops\Support\OpsAccessBridge;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;

/**
 * Provides read-oriented access visibility and a tightly scoped sync entry point.
 */
final class PermissionsPage extends OpsPage
{
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

    private function refreshOverview(): void
    {
        $this->overview = app(OpsAccessBridge::class)->permissionOverview();
    }
}
