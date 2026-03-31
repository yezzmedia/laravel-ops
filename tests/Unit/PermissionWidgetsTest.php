<?php

declare(strict_types=1);

use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Widgets\PermissionOverviewWidget;
use YezzMedia\Ops\Widgets\PermissionRuntimeStatusWidget;

it('builds permission overview stats from page data', function (): void {
    $widget = new class extends PermissionOverviewWidget
    {
        /**
         * @return array<Stat>
         */
        public function exposedStats(): array
        {
            return $this->getStats();
        }
    };

    $widget->overview = [
        'installed' => true,
        'available' => true,
        'error' => null,
        'store' => [
            'configPublished' => true,
            'migrationsPublished' => true,
            'pendingMigrations' => false,
            'ready' => true,
        ],
        'permissions' => [
            ['name' => 'ops.audit.view', 'package' => 'yezzmedia/laravel-ops', 'label' => 'View audit surfaces', 'synced' => true, 'roleHints' => [], 'assignedRoles' => []],
            ['name' => 'ops.access.view', 'package' => 'yezzmedia/laravel-ops', 'label' => 'View access operations', 'synced' => true, 'roleHints' => [], 'assignedRoles' => []],
        ],
        'roles' => [],
    ];

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(4)
        ->and($stats[0]->getLabel())->toBe('Declared permissions')
        ->and($stats[0]->getValue())->toBe(2)
        ->and($stats[1]->getValue())->toBe('Yes')
        ->and($stats[2]->getValue())->toBe('No')
        ->and($stats[3]->getValue())->toBe('Available');
});

it('builds permission runtime warning details from page data', function (): void {
    $widget = new class extends PermissionRuntimeStatusWidget
    {
        /**
         * @return array<Stat>
         */
        public function exposedStats(): array
        {
            return $this->getStats();
        }
    };

    $widget->overview = [
        'installed' => true,
        'available' => false,
        'error' => 'The access permission store runtime is incomplete.',
        'store' => [
            'configPublished' => false,
            'migrationsPublished' => false,
            'pendingMigrations' => false,
            'ready' => false,
        ],
        'permissions' => [],
        'roles' => [],
    ];

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(1)
        ->and($stats[0]->getValue())->toBe('Warning')
        ->and($stats[0]->getDescription())->toBe('The access permission store runtime is incomplete.');
});
