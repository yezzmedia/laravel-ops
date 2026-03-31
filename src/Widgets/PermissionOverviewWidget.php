<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Surfaces permission storage posture for operator review.
 */
class PermissionOverviewWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Permission posture';

    protected ?string $description = 'Declared permission volume, store readiness, and runtime bridge availability.';

    protected ?string $pollingInterval = null;

    /**
     * @var array{installed: bool, available: bool, error: ?string, store: array{configPublished: bool, migrationsPublished: bool, pendingMigrations: bool, ready: bool}, permissions: list<array{name: string, package: string, label: string, synced: bool, roleHints: list<string>, assignedRoles: list<string>}>, roles: list<array{name: string, permissionNames: list<string>}>}
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

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Declared permissions', count($this->overview['permissions']))
                ->description('Foundation-declared permissions visible to the ops surface.'),
            Stat::make('Store ready', $this->overview['store']['ready'] ? 'Yes' : 'No')
                ->description('Indicates whether the access permission store is ready for synchronization.'),
            Stat::make('Pending migrations', $this->overview['store']['pendingMigrations'] ? 'Yes' : 'No')
                ->description('Published access migrations that still need to be applied.'),
            Stat::make('Runtime bridge', $this->overview['available'] ? 'Available' : 'Limited')
                ->description('Shows whether the access permission runtime can be queried by ops.'),
        ];
    }
}
