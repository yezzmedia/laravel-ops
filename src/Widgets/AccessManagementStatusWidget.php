<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Surfaces the current access-management warning or safety posture.
 */
class AccessManagementStatusWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Management detail';

    protected ?string $description = 'Current runtime warnings and super-admin guard guidance for access management actions.';

    protected ?string $pollingInterval = null;

    /**
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnSpan = 'full';

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
            Stat::make('Status', $this->statusLabel())
                ->description($this->statusDescription()),
        ];
    }

    private function statusLabel(): string
    {
        if ($this->overview['error'] !== null) {
            return 'Warning';
        }

        if ($this->overview['superAdmin']['enabled']) {
            return 'Protected';
        }

        return 'Standard';
    }

    private function statusDescription(): string
    {
        if ($this->overview['error'] !== null) {
            return $this->overview['error'];
        }

        if ($this->overview['superAdmin']['enabled']) {
            return 'Super-admin removals are guarded against dropping below the minimum configured operator count.';
        }

        return 'Super-admin protection is currently not enabled for access management workflows.';
    }
}
