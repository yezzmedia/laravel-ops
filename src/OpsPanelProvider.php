<?php

declare(strict_types=1);

namespace YezzMedia\Ops;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use YezzMedia\Ops\Http\Middleware\AuthorizeOpsPanelAccess;
use YezzMedia\Ops\Pages\AccessManagementPage;
use YezzMedia\Ops\Pages\AuditEntryDetailsPage;
use YezzMedia\Ops\Pages\AuditTrailPage;
use YezzMedia\Ops\Pages\DoctorCheckDetailsPage;
use YezzMedia\Ops\Pages\FeaturesPage;
use YezzMedia\Ops\Pages\OpsDashboard;
use YezzMedia\Ops\Pages\PackageDetailsPage;
use YezzMedia\Ops\Pages\PackagesPage;
use YezzMedia\Ops\Pages\PermissionDetailsPage;
use YezzMedia\Ops\Pages\PermissionsPage;
use YezzMedia\Ops\Pages\RoleDetailsPage;
use YezzMedia\Ops\Pages\SystemHealthPage;
use YezzMedia\Ops\Support\OpsGuardResolver;
use YezzMedia\Ops\Widgets\FailingChecksWidget;
use YezzMedia\Ops\Widgets\InstalledPackagesWidget;
use YezzMedia\Ops\Widgets\RecentActivityWidget;
use YezzMedia\OpsInfrastructure\Filament\OpsInfrastructurePlugin;
use YezzMedia\OpsSecurity\Filament\OpsSecurityFilamentPlugin;
use YezzMedia\OpsSettings\Filament\OpsSettingsPlugin;
use YezzMedia\OpsSites\Filament\OpsSitesFilamentPlugin;

/**
 * Defines the dedicated Filament panel shell for operator-facing workflows.
 */
class OpsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $opsAccess = app(OpsGuardResolver::class)->resolve();

        return $panel
            ->id($this->panelId())
            ->path($this->panelPath())
            ->login()
            ->authGuard($opsAccess['guard'])
            ->pages([
                OpsDashboard::class,
                PackagesPage::class,
                PackageDetailsPage::class,
                FeaturesPage::class,
                SystemHealthPage::class,
                DoctorCheckDetailsPage::class,
                PermissionsPage::class,
                RoleDetailsPage::class,
                PermissionDetailsPage::class,
                AccessManagementPage::class,
                AuditTrailPage::class,
                AuditEntryDetailsPage::class,
            ])
            ->widgets([
                InstalledPackagesWidget::class,
                FailingChecksWidget::class,
                RecentActivityWidget::class,
            ])
            ->when(class_exists(OpsInfrastructurePlugin::class), fn ($p) => $p->plugin(OpsInfrastructurePlugin::make()))
            ->when(class_exists(OpsSitesFilamentPlugin::class), fn ($p) => $p->plugin(OpsSitesFilamentPlugin::make()))
            ->when(class_exists(OpsSecurityFilamentPlugin::class), fn ($p) => $p->plugin(OpsSecurityFilamentPlugin::make()))
            ->when(class_exists(OpsSettingsPlugin::class), fn ($p) => $p->plugin(OpsSettingsPlugin::make()))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                AuthorizeOpsPanelAccess::class,
            ], isPersistent: true);
    }

    private function panelId(): string
    {
        $panelId = config('ops.panel.id', 'ops');

        return is_string($panelId) && $panelId !== '' ? $panelId : 'ops';
    }

    private function panelPath(): string
    {
        $panelPath = config('ops.panel.path', 'ops');

        return is_string($panelPath) && $panelPath !== '' ? $panelPath : 'ops';
    }
}
