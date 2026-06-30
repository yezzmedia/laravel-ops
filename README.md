<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/yezzmedia/.github/main/profile/yezzmedia-dark.svg">
    <img src="https://raw.githubusercontent.com/yezzmedia/.github/main/profile/yezzmedia-light.svg" alt="Yezz Media" height="40">
  </picture>
</p>

<p align="center">
  <a href="https://packagist.org/packages/yezzmedia/laravel-ops"><img src="https://img.shields.io/packagist/v/yezzmedia/laravel-ops?style=flat-square" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/yezzmedia/laravel-ops"><img src="https://img.shields.io/packagist/php-v/yezzmedia/laravel-ops?style=flat-square" alt="PHP Version"></a>
  <a href="https://packagist.org/packages/yezzmedia/laravel-ops"><img src="https://img.shields.io/packagist/l/yezzmedia/laravel-ops?style=flat-square" alt="License"></a>
</p>

---

# Laravel Ops

`yezzmedia/laravel-ops` is the shared operations panel package for the Yezz Media Laravel platform.

It provides a Filament-based `/ops` panel with package, feature, diagnostics, audit, and access-facing operational surfaces on top of the normalized runtime exposed by foundation and, when installed, `yezzmedia/laravel-access`.

## Version

Current release: `0.2.0`

## Requirements

- PHP `^8.4`
- Laravel `^13.0` components
- `filament/filament ^5.0`
- `spatie/laravel-package-tools ^1.93`
- `yezzmedia/laravel-foundation ^0.1`

Optional:

- `yezzmedia/laravel-access ^0.1` for the preferred ops authorization and access-management workflows
- `spatie/laravel-activitylog` for audit-facing surfaces
- `spatie/laravel-health` for richer diagnostics posture
- `yezzmedia/laravel-ops-analytics ^0.1` for analytics posture, tracker, and consent-aware surfaces inside the shared ops panel
- `yezzmedia/laravel-ops-backups ^0.1` for backup posture and restore-readiness surfaces inside the shared ops panel
- `yezzmedia/laravel-ops-infrastructure ^0.1` for infrastructure posture inside the shared ops panel
- `yezzmedia/laravel-ops-sites ^0.1` for site inventory and assignment posture inside the shared ops panel
- `yezzmedia/laravel-ops-security ^0.1` for security posture and governance surfaces inside the shared ops panel
- `yezzmedia/laravel-ops-settings` for operator-managed settings UI inside the shared ops panel

## Installation

Install the package in the consuming Laravel application:

```bash
composer require yezzmedia/laravel-ops
```

The service providers are auto-discovered:

- `YezzMedia\Ops\OpsServiceProvider`
- `YezzMedia\Ops\OpsPanelProvider`

## Configuration

Publish the package config when you need to override panel defaults:

```bash
php artisan vendor:publish --provider="YezzMedia\Ops\OpsServiceProvider" --tag="config"
```

Default configuration:

```php
return [
    'panel' => [
        'id' => 'ops',
        'path' => 'ops',
        'guard' => null,
    ],
];
```

## What The Package Provides

### Shared ops panel shell

`OpsPanelProvider` defines a dedicated Filament panel mounted at `/ops`.

- uses the configured or resolved host auth guard
- applies a dedicated ops authorization middleware boundary
- registers the stable V1 ops pages and widgets
- conditionally loads companion plugins when the corresponding packages are installed:
  - `YezzMedia\OpsAnalytics\Filament\OpsAnalyticsFilamentPlugin`
  - `YezzMedia\OpsBackups\Filament\OpsBackupsFilamentPlugin`
  - `YezzMedia\OpsInfrastructure\Filament\OpsInfrastructurePlugin`
  - `YezzMedia\OpsSites\Filament\OpsSitesFilamentPlugin`
  - `YezzMedia\OpsSecurity\Filament\OpsSecurityFilamentPlugin`
  - `YezzMedia\OpsSettings\Filament\OpsSettingsPlugin`

### Stable ops pages

The package currently provides these operator-facing pages:

- `OpsDashboard`
- `PackagesPage`
- `FeaturesPage`
- `SystemHealthPage`
- `AuditTrailPage`
- `PermissionsPage`
- `AccessManagementPage`

The panel also registers detail pages that support drill-down workflows without adding extra top-level navigation entries:

- `PackageDetailsPage`
- `DoctorCheckDetailsPage`
- `RoleDetailsPage`
- `PermissionDetailsPage`
- `AuditEntryDetailsPage`

These surface the following routes:

- `/ops`
- `/ops/packages`
- `/ops/features`
- `/ops/diagnostics`
- `/ops/audit`
- `/ops/access/permissions`
- `/ops/access/manage`

### Widgets and summaries

The dashboard currently includes:

- `InstalledPackagesWidget`
- `FailingChecksWidget`
- `RecentActivityWidget`

These build on the package summary, diagnostics summary, and recent activity resolver layers instead of duplicating query or registry logic in widgets.

### Authorization model

Ops declares a stable permission surface through foundation:

- `ops.panel.access`
- `ops.dashboard.view`
- `ops.packages.view`
- `ops.features.view`
- `ops.diagnostics.view`
- `ops.runtime.view`
- `ops.audit.view`
- `ops.access.view`
- `ops.access.manage`

When `yezzmedia/laravel-access` is not installed, ops falls back to reduced mode and uses the host-owned `viewOpsPanel` / `canAccessPanel()` boundary.

When access is installed, the ops panel enforces the declared access permissions and unlocks the access-facing surfaces.

### Diagnostics and runtime posture

`SystemHealthPage` and the diagnostics widget stack build on:

- `RunSystemDiagnosticsAction`
- `OpsDiagnosticsSummaryResolver`
- `OpsDiagnosticsCacheManager`
- `OpsRuntimePostureResolver`

This keeps diagnostics collection and runtime posture aggregation in dedicated support classes instead of inside the page layer.

### Audit and access bridges

`AuditTrailPage` uses the recent-activity resolver stack and degrades cleanly when no supported audit backend exists.

`PermissionsPage` and `AccessManagementPage` use `OpsAccessBridge` to read from access-owned services rather than re-implementing permission-store or role-management logic inside ops.

Those access-facing surfaces can:

- inspect permission-store readiness
- synchronize declared permissions
- synchronize hinted roles
- assign persisted roles to a user
- remove persisted roles from a user

The actual write operations remain delegated to access-owned runtime services.

## Foundation integration

Ops registers itself with foundation through `OpsPlatformPackage`.

That package descriptor currently provides:

- package metadata for `yezzmedia/laravel-ops`
- the stable ops permission declarations
- an explicit ops-module surface, currently empty for V1
- security-governance declarations for the ops authentication entry point:
  - request `ops.request.auth.login-throttle`
  - requirement `ops.auth.login-throttle`

These declarations are intentionally observe-only. Ops declares the required operator login-throttle posture, while verification stays in `yezzmedia/laravel-ops-security` and concrete runtime enforcement stays in the host auth and middleware layer.

## Development

Available package scripts:

```bash
composer test
composer analyse
composer format
```

## License

MIT
