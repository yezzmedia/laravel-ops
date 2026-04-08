<?php

declare(strict_types=1);

use Filament\PanelRegistry;
use YezzMedia\Foundation\Contracts\DefinesPermissions;
use YezzMedia\Foundation\Contracts\PlatformPackage;
use YezzMedia\Foundation\Contracts\ProvidesOpsModules;
use YezzMedia\Foundation\Contracts\RegistersFeatures;
use YezzMedia\Foundation\Registry\FeatureRegistry;
use YezzMedia\Foundation\Registry\OpsModuleRegistry;
use YezzMedia\Foundation\Registry\PackageRegistry;
use YezzMedia\Foundation\Registry\PermissionRegistry;
use YezzMedia\Ops\Actions\RefreshAuditSnapshotAction;
use YezzMedia\Ops\Actions\RunSystemDiagnosticsAction;
use YezzMedia\Ops\Contracts\OpsAuditWriter;
use YezzMedia\Ops\OpsPlatformPackage;
use YezzMedia\Ops\Pages\AccessManagementPage;
use YezzMedia\Ops\Pages\AuditEntryDetailsPage;
use YezzMedia\Ops\Pages\AuditTrailPage;
use YezzMedia\Ops\Pages\FeaturesPage;
use YezzMedia\Ops\Pages\OpsDashboard;
use YezzMedia\Ops\Pages\PackageDetailsPage;
use YezzMedia\Ops\Pages\PackagesPage;
use YezzMedia\Ops\Pages\PermissionDetailsPage;
use YezzMedia\Ops\Pages\PermissionsPage;
use YezzMedia\Ops\Pages\RoleDetailsPage;
use YezzMedia\Ops\Pages\SystemHealthPage;
use YezzMedia\Ops\Support\OpsAccessBridge;
use YezzMedia\Ops\Support\OpsAuditEntryDetailsResolver;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;
use YezzMedia\Ops\Support\OpsDiagnosticsCacheManager;
use YezzMedia\Ops\Support\OpsDiagnosticsSummaryResolver;
use YezzMedia\Ops\Support\OpsFailingChecksWidgetDataResolver;
use YezzMedia\Ops\Support\OpsFeatureOverviewResolver;
use YezzMedia\Ops\Support\OpsGuardResolver;
use YezzMedia\Ops\Support\OpsIntegrationResolver;
use YezzMedia\Ops\Support\OpsNavigationResolver;
use YezzMedia\Ops\Support\OpsPackageDetailsResolver;
use YezzMedia\Ops\Support\OpsPackageOverviewResolver;
use YezzMedia\Ops\Support\OpsPackageSummaryCacheManager;
use YezzMedia\Ops\Support\OpsPackageSummaryResolver;
use YezzMedia\Ops\Support\OpsPermissionDetailsResolver;
use YezzMedia\Ops\Support\OpsRecentActivityCacheManager;
use YezzMedia\Ops\Support\OpsRecentActivityResolver;
use YezzMedia\Ops\Support\OpsRoleDetailsResolver;
use YezzMedia\Ops\Support\OpsRuntimePostureResolver;
use YezzMedia\Ops\Support\OpsSurfaceVisibilityResolver;
use YezzMedia\Ops\Widgets\FailingChecksWidget;
use YezzMedia\Ops\Widgets\InstalledPackagesWidget;
use YezzMedia\Ops\Widgets\RecentActivityWidget;
use YezzMedia\OpsInfrastructure\Filament\OpsInfrastructurePlugin;

it('registers the ops bootstrap surface', function (): void {
    $panel = app(PanelRegistry::class)->get('ops');

    expect(app(PackageRegistry::class)->has('yezzmedia/laravel-ops'))->toBeTrue()
        ->and(app(OpsGuardResolver::class))->toBeInstanceOf(OpsGuardResolver::class)
        ->and(app(OpsIntegrationResolver::class))->toBeInstanceOf(OpsIntegrationResolver::class)
        ->and(app(OpsSurfaceVisibilityResolver::class))->toBeInstanceOf(OpsSurfaceVisibilityResolver::class)
        ->and(app(OpsAuthorizationResolver::class))->toBeInstanceOf(OpsAuthorizationResolver::class)
        ->and(app(OpsDiagnosticsCacheManager::class))->toBeInstanceOf(OpsDiagnosticsCacheManager::class)
        ->and(app(OpsFailingChecksWidgetDataResolver::class))->toBeInstanceOf(OpsFailingChecksWidgetDataResolver::class)
        ->and(app(OpsDiagnosticsSummaryResolver::class))->toBeInstanceOf(OpsDiagnosticsSummaryResolver::class)
        ->and(app(OpsRuntimePostureResolver::class))->toBeInstanceOf(OpsRuntimePostureResolver::class)
        ->and(app(OpsPackageSummaryCacheManager::class))->toBeInstanceOf(OpsPackageSummaryCacheManager::class)
        ->and(app(OpsPackageSummaryResolver::class))->toBeInstanceOf(OpsPackageSummaryResolver::class)
        ->and(app(OpsPackageOverviewResolver::class))->toBeInstanceOf(OpsPackageOverviewResolver::class)
        ->and(app(OpsPackageDetailsResolver::class))->toBeInstanceOf(OpsPackageDetailsResolver::class)
        ->and(app(OpsFeatureOverviewResolver::class))->toBeInstanceOf(OpsFeatureOverviewResolver::class)
        ->and(app(OpsRecentActivityCacheManager::class))->toBeInstanceOf(OpsRecentActivityCacheManager::class)
        ->and(app(OpsRecentActivityResolver::class))->toBeInstanceOf(OpsRecentActivityResolver::class)
        ->and(app(OpsAuditEntryDetailsResolver::class))->toBeInstanceOf(OpsAuditEntryDetailsResolver::class)
        ->and(app(OpsAuditWriter::class))->toBeInstanceOf(OpsAuditWriter::class)
        ->and(app(OpsAccessBridge::class))->toBeInstanceOf(OpsAccessBridge::class)
        ->and(app(OpsPermissionDetailsResolver::class))->toBeInstanceOf(OpsPermissionDetailsResolver::class)
        ->and(app(OpsRoleDetailsResolver::class))->toBeInstanceOf(OpsRoleDetailsResolver::class)
        ->and(app(RunSystemDiagnosticsAction::class))->toBeInstanceOf(RunSystemDiagnosticsAction::class)
        ->and(app(RefreshAuditSnapshotAction::class))->toBeInstanceOf(RefreshAuditSnapshotAction::class)
        ->and(app(OpsNavigationResolver::class))->toBeInstanceOf(OpsNavigationResolver::class)
        ->and(app(FeatureRegistry::class)->forPackage('yezzmedia/laravel-ops')->pluck('name')->all())->toBe([
            'ops.packages',
            'ops.features',
            'ops.diagnostics',
            'ops.runtime',
            'ops.audit',
        ])
        ->and(app(OpsModuleRegistry::class)->forPackage('yezzmedia/laravel-ops'))->toHaveCount(0)
        ->and(app(PermissionRegistry::class)->forPackage('yezzmedia/laravel-ops')->pluck('name')->all())->toBe([
            'ops.panel.access',
            'ops.dashboard.view',
            'ops.packages.view',
            'ops.features.view',
            'ops.diagnostics.view',
            'ops.runtime.view',
            'ops.audit.view',
            'ops.access.view',
            'ops.access.manage',
        ])
        ->and($panel)->not->toBeNull()
        ->and($panel?->getId())->toBe('ops')
        ->and($panel?->getPath())->toBe('ops')
        ->and($panel?->getAuthGuard())->toBe('web')
        ->and($panel?->hasPlugin('ops-infrastructure'))->toBeTrue()
        ->and($panel?->getPlugin('ops-infrastructure'))->toBeInstanceOf(OpsInfrastructurePlugin::class)
        ->and($panel?->getPages())->toContain(OpsDashboard::class)
        ->and($panel?->getPages())->toContain(PackagesPage::class)
        ->and($panel?->getPages())->toContain(PackageDetailsPage::class)
        ->and($panel?->getPages())->toContain(FeaturesPage::class)
        ->and($panel?->getPages())->toContain(SystemHealthPage::class)
        ->and($panel?->getPages())->toContain(PermissionsPage::class)
        ->and($panel?->getPages())->toContain(PermissionDetailsPage::class)
        ->and($panel?->getPages())->toContain(RoleDetailsPage::class)
        ->and($panel?->getPages())->toContain(AccessManagementPage::class)
        ->and($panel?->getPages())->toContain(AuditTrailPage::class)
        ->and($panel?->getPages())->toContain(AuditEntryDetailsPage::class)
        ->and($panel?->getWidgets())->toContain(InstalledPackagesWidget::class)
        ->and($panel?->getWidgets())->toContain(FailingChecksWidget::class)
        ->and($panel?->getWidgets())->toContain(RecentActivityWidget::class);
});

it('merges the package configuration', function (): void {
    expect(config('ops.panel.id'))->toBe('ops')
        ->and(config('ops.panel.path'))->toBe('ops')
        ->and(config('ops.auth.guard'))->toBeNull()
        ->and(config('ops.auth.host_guard'))->toBe('web')
        ->and(config('ops.authorization.reduced_mode_ability'))->toBe('viewOpsPanel')
        ->and(config('ops.integrations.access.package'))->toBe('yezzmedia/laravel-access')
        ->and(config('ops.integrations.health.provider'))->toBe('Spatie\\Health\\HealthServiceProvider')
        ->and(config('ops.integrations.audit.provider'))->toBe('Spatie\\Activitylog\\ActivitylogServiceProvider')
        ->and(config('ops.integrations.audit.model'))->toBe('Spatie\\Activitylog\\Models\\Activity')
        ->and(config('ops.integrations.audit.logged_event'))->toBe('Spatie\\Activitylog\\Events\\ActivityLogged')
        ->and(config('ops.diagnostics.cooldown_seconds'))->toBe(30)
        ->and(config('ops.diagnostics.lock_seconds'))->toBe(30)
        ->and(config('ops.diagnostics.failing_checks_widget_ttl_seconds'))->toBe(30)
        ->and(config('ops.diagnostics.latest_summary_ttl_seconds'))->toBe(300)
        ->and(config('ops.packages.installed_widget_ttl_seconds'))->toBe(300)
        ->and(config('ops.audit.recent_activity_widget_ttl_seconds'))->toBe(30);
});

it('describes the approved ops package surface', function (): void {
    $package = new OpsPlatformPackage;
    $metadata = $package->metadata();
    $panelAccessPermission = collect($package->permissionDefinitions())->firstWhere('name', 'ops.panel.access');

    expect($package)->toBeInstanceOf(PlatformPackage::class)
        ->and($package)->toBeInstanceOf(DefinesPermissions::class)
        ->and($package)->toBeInstanceOf(ProvidesOpsModules::class)
        ->and($package)->toBeInstanceOf(RegistersFeatures::class)
        ->and($metadata->name)->toBe('yezzmedia/laravel-ops')
        ->and($metadata->vendor)->toBe('yezzmedia')
        ->and($metadata->packageClass)->toBe(OpsPlatformPackage::class)
        ->and($panelAccessPermission)->not->toBeNull()
        ->and($panelAccessPermission?->defaultRoleHints)->toBe(['super-admin'])
        ->and($package->permissionDefinitions())->toHaveCount(9)
        ->and($package->featureDefinitions())->toHaveCount(5)
        ->and($package->auditEventDefinitions())->toHaveCount(3)
        ->and($package->opsModuleDefinitions())->toBe([]);
});
