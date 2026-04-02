<?php

declare(strict_types=1);

use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Widgets\AccessManagementOverviewWidget;
use YezzMedia\Ops\Widgets\AccessManagementStatusWidget;

it('builds access management overview stats from page data', function (): void {
    $widget = new class extends AccessManagementOverviewWidget
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
        'superAdmin' => [
            'enabled' => true,
            'roleName' => 'super-admin',
            'minimumOperators' => 2,
            'operatorCount' => 3,
        ],
        'roles' => [],
    ];

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getValue())->toBe('Available')
        ->and($stats[1]->getValue())->toBe('super-admin')
        ->and($stats[2]->getValue())->toBe(3)
        ->and($stats[2]->getDescription())->toBe('Minimum: 2');
});

it('builds access management warning details from page data', function (): void {
    $widget = new class extends AccessManagementStatusWidget
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
        'error' => 'The configured host user model is unavailable for access management.',
        'superAdmin' => [
            'enabled' => false,
            'roleName' => null,
            'minimumOperators' => 2,
            'operatorCount' => 0,
        ],
        'roles' => [],
    ];

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(1)
        ->and($stats[0]->getValue())->toBe('Warning')
        ->and($stats[0]->getDescription())->toBe('The configured host user model is unavailable for access management.');
});
