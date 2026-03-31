<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Surfaces the current permission runtime message or warning posture.
 */
class PermissionRuntimeStatusWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Runtime detail';

    protected ?string $description = 'Access runtime warnings and current store posture relevant to permission visibility.';

    protected ?string $pollingInterval = null;

    /**
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnSpan = 'full';

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
            Stat::make('Status', $this->statusLabel())
                ->description($this->statusDescription()),
        ];
    }

    private function statusLabel(): string
    {
        if ($this->overview['error'] !== null) {
            return 'Warning';
        }

        if (! $this->overview['store']['ready']) {
            return 'Store incomplete';
        }

        return 'Ready';
    }

    private function statusDescription(): string
    {
        if ($this->overview['error'] !== null) {
            return $this->overview['error'];
        }

        if (! $this->overview['store']['ready']) {
            return 'The access permission store is not fully ready for synchronization yet.';
        }

        return 'The access permission store is ready for read-oriented visibility and synchronization workflows.';
    }
}
