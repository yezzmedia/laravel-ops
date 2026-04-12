# Changelog

All notable changes to `yezzmedia/laravel-ops` will be documented in this file.

The format is based on Keep a Changelog and this package follows Semantic Versioning.

## [Unreleased]

### Added

- security-governance declarations through foundation:
  - `ops.request.auth.login-throttle`
  - `ops.auth.login-throttle`
- conditional panel plugin loading for:
  - `yezzmedia/laravel-ops-analytics`
  - `yezzmedia/laravel-ops-backups`
  - `yezzmedia/laravel-ops-security`
  - `yezzmedia/laravel-ops-infrastructure`
  - `yezzmedia/laravel-ops-sites`
  - `yezzmedia/laravel-ops-settings`
- regression coverage for reusing access-store readiness checks across the permissions overview

### Changed

- access overview reads now reuse access-store snapshots so the permissions page avoids duplicate readiness checks in the same request
- the panel surface now includes package, doctor, role, permission, and audit-entry detail pages as part of the supported operator workflow

### Documentation

- documented the security-governance declarations, complete conditional companion plugin loading surface, optional companion package suggestions, and the full supported page inventory in the package README

## [0.1.0] - 2026-03-31

### Added

- foundation-aligned package bootstrap through `OpsServiceProvider`, `OpsPanelProvider`, and `OpsPlatformPackage`
- dedicated Filament ops panel mounted at `/ops`
- stable ops pages:
  - `OpsDashboard`
  - `PackagesPage`
  - `FeaturesPage`
  - `SystemHealthPage`
  - `AuditTrailPage`
  - `PermissionsPage`
  - `AccessManagementPage`
- dashboard widgets:
  - `InstalledPackagesWidget`
  - `FailingChecksWidget`
  - `RecentActivityWidget`
- diagnostics runtime through `RunSystemDiagnosticsAction`, `OpsDiagnosticsSummaryResolver`, and `OpsDiagnosticsCacheManager`
- package, feature, runtime, audit, and access overview resolvers for page-facing data aggregation
- access bridge integration for permission-store visibility, permission sync, role sync, and user-role mutations when `yezzmedia/laravel-access` is installed
- stable ops permission declarations for panel access, page visibility, runtime posture, audit visibility, and access management
- package Pest and PHPStan coverage for panel authorization, pages, widgets, and resolver behavior

### Changed

- established reduced-mode fallback behavior when `yezzmedia/laravel-access` is not installed
- kept access mutations delegated to access-owned runtime services instead of duplicating that behavior inside ops

### Documentation

- added the initial package README with supported surfaces, permissions, and integration behavior
