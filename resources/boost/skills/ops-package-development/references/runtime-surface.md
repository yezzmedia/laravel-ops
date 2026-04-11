# Approved V1 Ops Surface

The conservative ops package surface includes these functional groups:

## Panel shell and operator entry points

- register the dedicated ops Filament panel shell through `OpsPanelProvider`
- expose first-party ops pages for dashboard, package inventory, feature inventory, diagnostics, access, and audit visibility
- attach compatible companion Filament plugins only when their classes are available
- gate panel entry through ops guard resolution and explicit panel authorization middleware

## Registry projection and summaries

- project package, feature, permission, and third-party ops-module registry state into operator-facing summaries
- derive first-party and third-party entry points through `OpsNavigationResolver`
- expose package, feature, permission, role, audit-entry, and runtime posture detail resolvers
- keep package and feature overview logic aligned with the declared ops-module surface

## Diagnostics and integrations

- run foundation doctor diagnostics and project failing or warning posture into page and widget data
- surface optional host integrations for access, audit, and health-provider context without taking ownership of those runtimes
- read recent activity through the configured audit integration when available
- bridge access visibility through `OpsAccessBridge`

## Approved public types behind those functions

- `OpsPlatformPackage`
- `OpsServiceProvider`
- `OpsPanelProvider`
- `OpsNavigationResolver`
- `OpsPackageSummaryResolver`
- `OpsPackageOverviewResolver`
- `OpsPackageDetailsResolver`
- `OpsFeatureOverviewResolver`
- `OpsDiagnosticsSummaryResolver`
- `OpsRecentActivityResolver`
- `OpsRuntimePostureResolver`
- `OpsAccessBridge`
- `OpsAuthorizationResolver`
- `RunSystemDiagnosticsAction`
- `RefreshAuditSnapshotAction`

Current declared features are:

- `ops.packages`
- `ops.features`
- `ops.diagnostics`
- `ops.runtime`
- `ops.audit`

Current security governance entries are:

- `ops.request.auth.login-throttle`
- `ops.auth.login-throttle`

`OpsPlatformPackage` currently declares no package-owned ops modules on this branch. Keep navigation behavior aligned with that current surface unless the package surface is intentionally expanded.
