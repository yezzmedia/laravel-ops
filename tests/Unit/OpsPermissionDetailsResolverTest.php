<?php

declare(strict_types=1);

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use YezzMedia\Ops\Support\OpsAccessBridge;
use YezzMedia\Ops\Support\OpsPermissionDetailsResolver;

it('builds permission details from the access permission overview', function (): void {
    $access = new class
    {
        public function permissionOverview(): array
        {
            return [
                'permissions' => [
                    [
                        'name' => 'ops.audit.view',
                        'package' => 'yezzmedia/laravel-ops',
                        'packageDescription' => 'Shared operations panel package.',
                        'label' => 'View audit surfaces',
                        'description' => 'View audit-facing operational visibility surfaces.',
                        'synced' => true,
                        'roleHints' => ['auditor'],
                        'roleHintsCount' => 1,
                        'assignedRoles' => ['super-admin'],
                        'assignedRoleCount' => 1,
                    ],
                ],
            ];
        }
    };

    app()->instance(OpsAccessBridge::class, $access);

    $details = app(OpsPermissionDetailsResolver::class)->resolve('ops.audit.view');

    expect($details['summary'])->toMatchArray([
        'name' => 'ops.audit.view',
        'label' => 'View audit surfaces',
        'package' => 'yezzmedia/laravel-ops',
        'packageDescription' => 'Shared operations panel package.',
        'description' => 'View audit-facing operational visibility surfaces.',
        'syncedLabel' => 'Synced',
        'syncedTone' => 'success',
        'roleHintsCount' => 1,
        'assignedRoleCount' => 1,
    ])
        ->and($details['roleHints'])->toBe(['auditor'])
        ->and($details['roleHintsLabel'])->toBe('auditor')
        ->and($details['assignedRoles'])->toBe(['super-admin'])
        ->and($details['assignedRolesLabel'])->toBe('super-admin');
});

it('fails when the requested permission is missing from the snapshot', function (): void {
    $access = new class
    {
        public function permissionOverview(): array
        {
            return ['permissions' => []];
        }
    };

    app()->instance(OpsAccessBridge::class, $access);

    expect(fn () => app(OpsPermissionDetailsResolver::class)->resolve('ops.audit.view'))
        ->toThrow(NotFoundHttpException::class);
});
