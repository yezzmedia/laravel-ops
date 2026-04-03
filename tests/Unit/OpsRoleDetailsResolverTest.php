<?php

declare(strict_types=1);

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use YezzMedia\Ops\Support\OpsAccessBridge;
use YezzMedia\Ops\Support\OpsRoleDetailsResolver;

it('builds role details from the access management overview', function (): void {
    $access = new class
    {
        public function managementOverview(): array
        {
            return [
                'roles' => [
                    [
                        'name' => 'super-admin',
                        'permissionCount' => 2,
                        'assignmentCount' => 3,
                        'permissionNames' => ['ops.audit.view', 'ops.access.manage'],
                        'permissionNamesLabel' => 'ops.audit.view, ops.access.manage',
                    ],
                ],
            ];
        }
    };

    app()->instance(OpsAccessBridge::class, $access);

    $details = app(OpsRoleDetailsResolver::class)->resolve('super-admin');

    expect($details['summary'])->toMatchArray([
        'name' => 'super-admin',
        'permissionCount' => 2,
        'assignmentCount' => 3,
        'isSuperAdminRole' => true,
        'superAdminStatus' => 'Super-admin',
    ])
        ->and($details['summary']['superAdminStatus'])->toBe('Super-admin')
        ->and($details['permissionNames'])->toBe(['ops.audit.view', 'ops.access.manage'])
        ->and($details['permissionNamesLabel'])->toBe('ops.audit.view, ops.access.manage');
});

it('fails when the requested role is missing from the snapshot', function (): void {
    $access = new class
    {
        public function managementOverview(): array
        {
            return ['roles' => []];
        }
    };

    app()->instance(OpsAccessBridge::class, $access);

    expect(fn () => app(OpsRoleDetailsResolver::class)->resolve('super-admin'))
        ->toThrow(NotFoundHttpException::class);
});
