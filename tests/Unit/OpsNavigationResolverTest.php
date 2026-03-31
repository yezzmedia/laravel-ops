<?php

declare(strict_types=1);

use YezzMedia\Access\AccessPlatformPackage;
use YezzMedia\Foundation\Data\OpsModuleDefinition;
use YezzMedia\Foundation\Registry\OpsModuleRegistry;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Support\OpsNavigationResolver;

it('returns the stable top-level navigation areas even without contributed modules', function (): void {
    expect(array_keys(app(OpsNavigationResolver::class)->resolve()))->toBe([
        'Dashboard',
        'Packages',
        'Features',
        'Diagnostics',
        'Access',
        'Audit',
    ]);
});

it('hides access-contributed page modules in reduced mode', function (): void {
    $registry = app(OpsModuleRegistry::class);

    $registry->register(new OpsModuleDefinition('diagnostics.queue', 'yezzmedia/laravel-ops', 'Queue posture', 'page', 'ops.diagnostics.view'));
    $registry->register(new OpsModuleDefinition('access.assignments', 'yezzmedia/laravel-access', 'Assignments', 'page', 'ops.access.manage'));
    $registry->register(new OpsModuleDefinition('audit.trail', 'yezzmedia/laravel-ops', 'Audit trail', 'page', 'ops.audit.view'));
    $registry->register(new OpsModuleDefinition('content.settings', 'yezzmedia/laravel-content', 'Content settings', 'page', 'content.settings.manage'));
    $registry->register(new OpsModuleDefinition('content.summary', 'yezzmedia/laravel-content', 'Content summary', 'widget'));
    $registry->register(new OpsModuleDefinition('content.sync', 'yezzmedia/laravel-content', 'Sync content', 'action'));

    $navigation = app(OpsNavigationResolver::class)->resolve();

    expect($navigation['Diagnostics'])->toBe([
        [
            'key' => 'diagnostics.queue',
            'package' => 'yezzmedia/laravel-ops',
            'label' => 'Queue posture',
            'type' => 'page',
            'permissionHint' => 'ops.diagnostics.view',
        ],
    ])
        ->and($navigation['Access'])->toBe([])
        ->and($navigation['Audit'])->toBe([
            [
                'key' => 'audit.trail',
                'package' => 'yezzmedia/laravel-ops',
                'label' => 'Audit trail',
                'type' => 'page',
                'permissionHint' => 'ops.audit.view',
            ],
        ])
        ->and($navigation['Packages'])->toBe([
            'yezzmedia/laravel-content' => [
                [
                    'key' => 'content.settings',
                    'package' => 'yezzmedia/laravel-content',
                    'label' => 'Content settings',
                    'type' => 'page',
                    'permissionHint' => 'content.settings.manage',
                ],
            ],
        ]);
});

it('shows access-contributed modules when access integration is active', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $registry = app(OpsModuleRegistry::class);
    $registry->register(new OpsModuleDefinition('access.assignments', 'yezzmedia/laravel-access', 'Assignments', 'page', 'ops.access.manage'));

    $navigation = app(OpsNavigationResolver::class)->resolve();

    expect($navigation['Access'])->toBe([
        [
            'key' => 'access.assignments',
            'package' => 'yezzmedia/laravel-access',
            'label' => 'Assignments',
            'type' => 'page',
            'permissionHint' => 'ops.access.manage',
        ],
    ]);
});
