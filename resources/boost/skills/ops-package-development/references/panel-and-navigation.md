# Panel And Navigation Rules

Use these rules when changing the ops panel shell or its registry-driven navigation.

## First-party panel surface

`OpsPanelProvider` currently owns these first-party pages:

- `OpsDashboard`
- `PackagesPage`
- `PackageDetailsPage`
- `FeaturesPage`
- `SystemHealthPage`
- `DoctorCheckDetailsPage`
- `PermissionsPage`
- `RoleDetailsPage`
- `PermissionDetailsPage`
- `AccessManagementPage`
- `AuditTrailPage`
- `AuditEntryDetailsPage`

It also owns these first-party widgets:

- `InstalledPackagesWidget`
- `FailingChecksWidget`
- `RecentActivityWidget`

## Current declaration reality

`OpsPlatformPackage` currently declares no package-owned ops modules on this branch. The first-party pages are still registered directly through `OpsPanelProvider`.

## Navigation projection rules

`OpsNavigationResolver` maps module prefixes into curated areas:

- `diagnostics.` -> `Diagnostics`
- `access.` -> `Access`
- `audit.` -> `Audit`
- other page modules -> `Packages`

Keep this resolver as the canonical mapping surface. Avoid duplicating these rules in page classes or summary resolvers.

## Companion plugin loading

The ops panel may attach these plugins when their classes exist:

- infrastructure
- sites
- security
- settings

Treat those integrations as optional runtime discovery, not unconditional package requirements.
