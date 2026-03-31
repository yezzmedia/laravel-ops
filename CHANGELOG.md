# Changelog

All notable changes to `yezzmedia/laravel-ops` will be documented in this file.

The format is based on Keep a Changelog and this package follows Semantic Versioning.

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
