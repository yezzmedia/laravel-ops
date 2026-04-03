<?php

declare(strict_types=1);

namespace YezzMedia\Ops;

use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use YezzMedia\Foundation\Doctor\DoctorManager;
use YezzMedia\Foundation\Registry\FeatureRegistry;
use YezzMedia\Foundation\Registry\OpsModuleRegistry;
use YezzMedia\Foundation\Registry\PackageRegistry;
use YezzMedia\Foundation\Registry\PermissionRegistry;
use YezzMedia\Foundation\Support\CacheKeyFactory;
use YezzMedia\Foundation\Support\IntegrationManager;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Actions\RunSystemDiagnosticsAction;
use YezzMedia\Ops\Support\ActivitylogRecentActivityReader;
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
use YezzMedia\Ops\Support\OpsRecentActivityCacheManager;
use YezzMedia\Ops\Support\OpsRecentActivityResolver;
use YezzMedia\Ops\Support\OpsRuntimePostureResolver;
use YezzMedia\Ops\Support\OpsSurfaceVisibilityResolver;

/**
 * Boots the ops package without mixing panel concerns into package registration.
 */
class OpsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-ops')
            ->hasConfigFile()
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(OpsGuardResolver::class);
        $this->app->singleton(OpsDiagnosticsCacheManager::class, function (): OpsDiagnosticsCacheManager {
            return new OpsDiagnosticsCacheManager($this->app->make(CacheKeyFactory::class));
        });
        $this->app->singleton(OpsPackageSummaryCacheManager::class, function (): OpsPackageSummaryCacheManager {
            return new OpsPackageSummaryCacheManager($this->app->make(CacheKeyFactory::class));
        });
        $this->app->singleton(OpsRecentActivityCacheManager::class, function (): OpsRecentActivityCacheManager {
            return new OpsRecentActivityCacheManager($this->app->make(CacheKeyFactory::class));
        });
        $this->app->singleton(ActivitylogRecentActivityReader::class);
        $this->app->singleton(OpsIntegrationResolver::class, function (): OpsIntegrationResolver {
            return new OpsIntegrationResolver($this->app->make(IntegrationManager::class));
        });
        $this->app->singleton(OpsSurfaceVisibilityResolver::class, function (): OpsSurfaceVisibilityResolver {
            return new OpsSurfaceVisibilityResolver($this->app->make(OpsIntegrationResolver::class));
        });
        $this->app->singleton(OpsAuthorizationResolver::class, function (): OpsAuthorizationResolver {
            return new OpsAuthorizationResolver(
                guards: $this->app->make(OpsGuardResolver::class),
                integrations: $this->app->make(OpsIntegrationResolver::class),
                visibility: $this->app->make(OpsSurfaceVisibilityResolver::class),
            );
        });
        $this->app->singleton(OpsDiagnosticsSummaryResolver::class, function (): OpsDiagnosticsSummaryResolver {
            return new OpsDiagnosticsSummaryResolver(
                doctor: $this->app->make(DoctorManager::class),
                integrations: $this->app->make(OpsIntegrationResolver::class),
            );
        });
        $this->app->singleton(OpsFailingChecksWidgetDataResolver::class, function (): OpsFailingChecksWidgetDataResolver {
            return new OpsFailingChecksWidgetDataResolver(
                cache: $this->app->make(OpsDiagnosticsCacheManager::class),
                summaries: $this->app->make(OpsDiagnosticsSummaryResolver::class),
            );
        });
        $this->app->singleton(OpsPackageSummaryResolver::class, function (): OpsPackageSummaryResolver {
            return new OpsPackageSummaryResolver(
                packages: $this->app->make(PackageRegistry::class),
                features: $this->app->make(FeatureRegistry::class),
                navigation: $this->app->make(OpsNavigationResolver::class),
                cache: $this->app->make(OpsPackageSummaryCacheManager::class),
            );
        });
        $this->app->singleton(OpsRecentActivityResolver::class, function (): OpsRecentActivityResolver {
            return new OpsRecentActivityResolver(
                integrations: $this->app->make(OpsIntegrationResolver::class),
                cache: $this->app->make(OpsRecentActivityCacheManager::class),
                activitylog: $this->app->make(ActivitylogRecentActivityReader::class),
            );
        });
        $this->app->singleton(OpsAuditEntryDetailsResolver::class, function (): OpsAuditEntryDetailsResolver {
            return new OpsAuditEntryDetailsResolver(
                summary: $this->app->make(OpsRecentActivityResolver::class),
            );
        });
        $this->app->singleton(OpsNavigationResolver::class, function (): OpsNavigationResolver {
            return new OpsNavigationResolver(
                opsModules: $this->app->make(OpsModuleRegistry::class),
                visibility: $this->app->make(OpsSurfaceVisibilityResolver::class),
            );
        });
        $this->app->singleton(OpsPackageOverviewResolver::class, function (): OpsPackageOverviewResolver {
            return new OpsPackageOverviewResolver(
                packages: $this->app->make(PackageRegistry::class),
                features: $this->app->make(FeatureRegistry::class),
                permissions: $this->app->make(PermissionRegistry::class),
                opsModules: $this->app->make(OpsModuleRegistry::class),
                navigation: $this->app->make(OpsNavigationResolver::class),
            );
        });
        $this->app->singleton(OpsPackageDetailsResolver::class, function (): OpsPackageDetailsResolver {
            return new OpsPackageDetailsResolver(
                packages: $this->app->make(PackageRegistry::class),
                features: $this->app->make(FeatureRegistry::class),
                permissions: $this->app->make(PermissionRegistry::class),
                opsModules: $this->app->make(OpsModuleRegistry::class),
                navigation: $this->app->make(OpsNavigationResolver::class),
                overview: $this->app->make(OpsPackageOverviewResolver::class),
            );
        });
        $this->app->singleton(OpsFeatureOverviewResolver::class, function (): OpsFeatureOverviewResolver {
            return new OpsFeatureOverviewResolver(
                features: $this->app->make(FeatureRegistry::class),
                packages: $this->app->make(PackageRegistry::class),
                navigation: $this->app->make(OpsNavigationResolver::class),
            );
        });
        $this->app->singleton(OpsRuntimePostureResolver::class, function (): OpsRuntimePostureResolver {
            return new OpsRuntimePostureResolver(
                guards: $this->app->make(OpsGuardResolver::class),
                integrations: $this->app->make(OpsIntegrationResolver::class),
            );
        });
        $this->app->singleton(OpsAccessBridge::class, function (): OpsAccessBridge {
            return new OpsAccessBridge(
                permissions: $this->app->make(PermissionRegistry::class),
                integrations: $this->app->make(OpsIntegrationResolver::class),
            );
        });
        $this->app->singleton(RunSystemDiagnosticsAction::class, function (): RunSystemDiagnosticsAction {
            return new RunSystemDiagnosticsAction(
                authorization: $this->app->make(OpsAuthorizationResolver::class),
                cache: $this->app->make(OpsDiagnosticsCacheManager::class),
                summaries: $this->app->make(OpsDiagnosticsSummaryResolver::class),
                guards: $this->app->make(OpsGuardResolver::class),
            );
        });
    }

    public function packageBooted(): void
    {
        $this->app->make(PlatformPackageRegistrar::class)->register(new OpsPlatformPackage);

        $this->registerRecentActivityInvalidation();
    }

    private function registerRecentActivityInvalidation(): void
    {
        $eventClass = config('ops.integrations.audit.logged_event');

        if (! is_string($eventClass) || $eventClass === '' || ! class_exists($eventClass)) {
            return;
        }

        Event::listen($eventClass, function (): void {
            $this->app->make(OpsRecentActivityCacheManager::class)->invalidate();
        });
    }
}
