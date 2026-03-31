<?php

declare(strict_types=1);

namespace YezzMedia\Ops;

use YezzMedia\Foundation\Contracts\DefinesPermissions;
use YezzMedia\Foundation\Contracts\PlatformPackage;
use YezzMedia\Foundation\Contracts\ProvidesOpsModules;
use YezzMedia\Foundation\Data\OpsModuleDefinition;
use YezzMedia\Foundation\Data\PackageMetadata;
use YezzMedia\Foundation\Data\PermissionDefinition;

/**
 * Describes the stable ops-owned package surface for foundation registration.
 */
final class OpsPlatformPackage implements DefinesPermissions, PlatformPackage, ProvidesOpsModules
{
    public function metadata(): PackageMetadata
    {
        return new PackageMetadata(
            name: 'yezzmedia/laravel-ops',
            vendor: 'yezzmedia',
            description: 'Shared operations panel package for the Yezz Media Laravel website platform.',
            packageClass: self::class,
        );
    }

    /**
     * @return array<int, PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            new PermissionDefinition('ops.panel.access', 'yezzmedia/laravel-ops', 'Access the ops panel', 'Enter the shared operations panel.'),
            new PermissionDefinition('ops.dashboard.view', 'yezzmedia/laravel-ops', 'View the ops dashboard', 'View dashboard-level operations posture and summaries.'),
            new PermissionDefinition('ops.packages.view', 'yezzmedia/laravel-ops', 'View installed packages', 'View installed package posture and readiness summaries.'),
            new PermissionDefinition('ops.features.view', 'yezzmedia/laravel-ops', 'View platform features', 'View feature-level platform status and ownership summaries.'),
            new PermissionDefinition('ops.diagnostics.view', 'yezzmedia/laravel-ops', 'View diagnostics', 'View diagnostics, doctor results, and readiness posture.'),
            new PermissionDefinition('ops.runtime.view', 'yezzmedia/laravel-ops', 'View runtime posture', 'View runtime and infrastructure subsections inside diagnostics surfaces.'),
            new PermissionDefinition('ops.audit.view', 'yezzmedia/laravel-ops', 'View audit surfaces', 'View audit-facing operational visibility surfaces.'),
            new PermissionDefinition('ops.access.view', 'yezzmedia/laravel-ops', 'View access operations', 'View access-related operational visibility surfaces.'),
            new PermissionDefinition('ops.access.manage', 'yezzmedia/laravel-ops', 'Manage access operations', 'Perform access-management mutations through ops workflows.'),
        ];
    }

    /**
     * @return array<int, OpsModuleDefinition>
     */
    public function opsModuleDefinitions(): array
    {
        return [];
    }
}
