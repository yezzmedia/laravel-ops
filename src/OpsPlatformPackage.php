<?php

declare(strict_types=1);

namespace YezzMedia\Ops;

use YezzMedia\Foundation\Contracts\DefinesAuditEvents;
use YezzMedia\Foundation\Contracts\DefinesPermissions;
use YezzMedia\Foundation\Contracts\DefinesSecurityRequests;
use YezzMedia\Foundation\Contracts\DefinesSecurityRequirements;
use YezzMedia\Foundation\Contracts\PlatformPackage;
use YezzMedia\Foundation\Contracts\ProvidesOpsModules;
use YezzMedia\Foundation\Contracts\RegistersFeatures;
use YezzMedia\Foundation\Data\AuditEventDefinition;
use YezzMedia\Foundation\Data\FeatureDefinition;
use YezzMedia\Foundation\Data\OpsModuleDefinition;
use YezzMedia\Foundation\Data\PackageMetadata;
use YezzMedia\Foundation\Data\PermissionDefinition;
use YezzMedia\Foundation\Data\SecurityRequestDefinition;
use YezzMedia\Foundation\Data\SecurityRequirementDefinition;

/**
 * Describes the stable ops-owned package surface for foundation registration.
 */
final class OpsPlatformPackage implements DefinesAuditEvents, DefinesPermissions, DefinesSecurityRequests, DefinesSecurityRequirements, PlatformPackage, ProvidesOpsModules, RegistersFeatures
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
            new PermissionDefinition(
                'ops.panel.access',
                'yezzmedia/laravel-ops',
                'Access the ops panel',
                'Enter the shared operations panel.',
                defaultRoleHints: ['super-admin'],
            ),
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
     * @return array<int, FeatureDefinition>
     */
    public function featureDefinitions(): array
    {
        return [
            new FeatureDefinition(
                'ops.packages',
                'yezzmedia/laravel-ops',
                'Package visibility',
                'Curated visibility into installed platform packages, their posture, contributions, and operator entry points.',
            ),
            new FeatureDefinition(
                'ops.features',
                'yezzmedia/laravel-ops',
                'Feature visibility',
                'Curated visibility into registered platform features, their ownership, and related operator entry points.',
            ),
            new FeatureDefinition(
                'ops.diagnostics',
                'yezzmedia/laravel-ops',
                'Diagnostics',
                'Operator-facing diagnostics, doctor results, and readiness visibility for the platform.',
            ),
            new FeatureDefinition(
                'ops.runtime',
                'yezzmedia/laravel-ops',
                'Runtime posture',
                'Curated runtime and infrastructure posture visibility within ops diagnostics surfaces.',
            ),
            new FeatureDefinition(
                'ops.audit',
                'yezzmedia/laravel-ops',
                'Audit visibility',
                'Operator-facing audit visibility and recent operational activity when a supported audit backend is available.',
            ),
        ];
    }

    /**
     * @return array<int, AuditEventDefinition>
     */
    public function auditEventDefinitions(): array
    {
        return [
            new AuditEventDefinition(
                key: 'ops.diagnostics.refreshed',
                package: 'yezzmedia/laravel-ops',
                action: 'refreshed',
                subjectType: 'diagnostics_snapshot',
                description: 'Ops diagnostics were refreshed.',
                severity: 'info',
                contextKeys: ['status', 'failing_count', 'warning_count', 'completed_at'],
            ),
            new AuditEventDefinition(
                key: 'ops.diagnostics.refresh_failed',
                package: 'yezzmedia/laravel-ops',
                action: 'failed',
                subjectType: 'diagnostics_snapshot',
                description: 'Ops diagnostics refresh failed.',
                severity: 'warning',
                contextKeys: ['operator_id', 'status', 'reason', 'failing_count', 'warning_count', 'completed_at'],
            ),
            new AuditEventDefinition(
                key: 'ops.audit.snapshot_refreshed',
                package: 'yezzmedia/laravel-ops',
                action: 'refreshed',
                subjectType: 'audit_snapshot',
                description: 'Ops audit snapshot was refreshed.',
                severity: 'info',
                contextKeys: ['backend', 'status', 'activity_count', 'cached_at', 'source'],
            ),
        ];
    }

    /**
     * @return array<int, OpsModuleDefinition>
     */
    public function opsModuleDefinitions(): array
    {
        return [];
    }

    /**
     * @return array<int, SecurityRequestDefinition>
     */
    public function securityRequestDefinitions(): array
    {
        return [
            new SecurityRequestDefinition(
                key: 'ops.request.auth.login-throttle',
                package: 'yezzmedia/laravel-ops',
                domain: 'auth',
                control: 'login_throttle',
                scope: 'ops-panel',
                requestedLevel: 'required',
                requestedEnforcementMode: 'observe_only',
                description: 'The ops panel should expose operator login throttling as a declared control for central security review.',
                payloadSchema: [
                    'panel' => 'Target panel identifier.',
                    'guard' => 'Resolved authentication guard for operator access.',
                    'provider' => 'Login provider or entry surface.',
                ],
                allowedPreviewFields: ['panel', 'guard', 'provider'],
                notes: 'The current ops package declares operator login throttling intent and leaves runtime verification to ops-security.',
            ),
        ];
    }

    /**
     * @return array<int, SecurityRequirementDefinition>
     */
    public function securityRequirementDefinitions(): array
    {
        return [
            new SecurityRequirementDefinition(
                key: 'ops.auth.login-throttle',
                package: 'yezzmedia/laravel-ops',
                domain: 'auth',
                control: 'login_throttle',
                level: 'required',
                scope: 'ops-panel',
                description: 'Operator login entry points should be protected by login throttling.',
                enforcementMode: 'observe_only',
                appliesTo: ['ops-panel', 'login'],
                notes: 'The ops package declares the requirement while governance verification determines whether the active auth runtime satisfies it.',
            ),
        ];
    }
}
