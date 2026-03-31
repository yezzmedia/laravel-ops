<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Surfaces access-management posture for operator workflows.
 */
class AccessManagementOverviewWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Access management posture';

    protected ?string $description = 'Runtime bridge availability, super-admin role posture, and qualified operator coverage.';

    protected ?string $pollingInterval = null;

    /**
     * @var array{installed: bool, available: bool, error: ?string, superAdmin: array{enabled: bool, roleName: ?string, minimumOperators: int, operatorCount: int}, roles: list<array{name: string, permissionCount: int, assignmentCount: int, permissionNames: list<string>}>}
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

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Runtime bridge', $this->overview['available'] ? 'Available' : 'Limited')
                ->description('Shows whether write-capable access runtime services are currently available.'),
            Stat::make('Super-admin role', $this->overview['superAdmin']['roleName'] ?? 'Disabled')
                ->description('Configured elevated role protected by the super-admin safety guard.'),
            Stat::make('Qualified operators', $this->overview['superAdmin']['operatorCount'])
                ->description(sprintf('Minimum: %d', $this->overview['superAdmin']['minimumOperators'])),
        ];
    }
}
