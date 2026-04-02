# 003 Ops Planning Decisions

## Fold-Back Status

These planning decisions have been carried back into the primary `003-ops` architecture documents.

The canonical wording now lives in:

- `/home/yezz/Developement/plan/website/packages/yezzmedia/foundation/docs/architecture/003-ops-laravel.md`
- `/home/yezz/Developement/plan/website/packages/yezzmedia/foundation/docs/architecture/003-ops-reference.md`

This file remains as package-local historical context for how those decisions were prepared before the primary architecture source was updated.

## Purpose

This document recorded planning decisions for the current `003-ops` architecture direction before they were carried back into the primary architecture source.

It is now retained as package-local historical planning context rather than the canonical source of truth.

## Decision 1: Filament Panel Access Must Use `FilamentUser`

`laravel-ops` V1 must align with Filament's panel authorization model.

### Approved rule

- the host application's authenticatable user model must implement `Filament\Models\Contracts\FilamentUser`
- the host-owned `canAccessPanel(Panel $panel): bool` method is the explicit panel authorization boundary for `laravel-ops`
- `OpsGuardResolver` resolves the guard used by the ops panel
- `OpsPanelProvider` must consume the resolved guard through Filament panel configuration
- a resolved guard never replaces `canAccessPanel()`

### Why this is required

Filament 5 expects explicit panel authorization for non-local environments. A custom ops package must not invent a parallel panel-entry rule that conflicts with Filament's own access model.

### Effective access model

Entering the ops panel requires all applicable checks to pass:

1. the request is authenticated through the guard resolved by `OpsGuardResolver`
2. the authenticated user is a host user that implements `FilamentUser`
3. the authenticated user returns `true` from `canAccessPanel($panel)` for the ops panel
4. in access-integrated mode, the user also satisfies the access-owned permission boundary, including `ops.panel.access`
5. in reduced mode, the host still provides an explicit ops access boundary and the panel remains fail-closed
6. production-ready usage still requires MFA and minimum-super-admin safety according to the package plan

### Guard model clarification

- `OpsGuardResolver` remains responsible for guard resolution only
- a dedicated ops guard is allowed when the host application provides one
- a standard host guard is also allowed when the host application keeps an explicit ops boundary on top of it
- neither path may bypass `canAccessPanel()`
- neither path may silently widen access because a user is authenticated

### Reduced-mode clarification

Reduced mode without `laravel-access` still requires:

- an authenticated host user
- `FilamentUser`
- a positive `canAccessPanel($panel)` result for the ops panel
- an explicit host-owned reduced-mode access boundary

Reduced mode must remain visibility-oriented and fail closed.

### Reference-level wording to carry back

The `003-ops` plan and reference should explicitly state:

- `FilamentUser` is required for host users that can enter the ops panel
- `canAccessPanel()` is the host-owned panel boundary
- `OpsGuardResolver` resolves the auth guard, not a second standalone panel-authorization system
- access-integrated authorization layers on top of Filament panel access instead of replacing it

## Decision 2: V1 Navigation Uses Key-Conventions, Not a New DTO Field

V1 should keep the current foundation ops-module DTO unchanged.

### Approved rule

- `OpsModuleDefinition` stays unchanged in V1
- V1 does not add an `area`, `section`, or similar navigation-placement field to the foundation DTO
- `OpsNavigationResolver` maps module contributions into controlled navigation areas using stable key conventions and module type rules

### Why this is the preferred V1 approach

The current foundation surface already supports package-owned ops-module declarations through `OpsModuleDefinition`.
Adding placement metadata before there is proven pressure would enlarge the public compatibility surface too early.

V1 only needs predictable operator navigation, not a fully general package-controlled menu layout language.

### V1 mapping rules

The top-level operator navigation remains centrally owned by `laravel-ops`:

- `Dashboard`
- `Packages`
- `Features`
- `Diagnostics`
- `Access`
- `Audit`

`OpsNavigationResolver` should apply the following mapping rules to contributed modules:

1. module contributions never create new top-level navigation groups
2. modules with keys beginning with `diagnostics.` map into `Diagnostics`
3. modules with keys beginning with `access.` map into `Access`
4. modules with keys beginning with `audit.` map into `Audit`
5. contributed modules of type `page` that do not match a reserved area prefix map into `Packages`, grouped under their owning package
6. contributed modules of type `widget` never create automatic navigation entries
7. contributed modules of type `action` never create automatic navigation entries

### Ownership rule

Packages may contribute modules, but they do not own the final menu tree.

- `laravel-ops` owns the final navigation model
- contributing packages own their module definitions and their underlying business logic
- widgets and actions remain discoverable through pages and dashboards, not direct automatic menu injection

### Fallback rule

When a page contribution does not match a reserved ops area prefix, the safe V1 fallback is:

- treat it as a package-related operator page
- place it under `Packages`
- group it by the owning package name

This keeps navigation predictable without adding new DTO surface prematurely.

### Future upgrade rule

A later foundation DTO expansion should only happen when real package needs prove that key-based mapping is no longer sufficient.

That change should be made deliberately in a future reviewed change set and not be assumed by V1.

### Reference-level wording to carry back

The `003-ops` plan and reference should explicitly state:

- V1 navigation placement is resolved by `OpsNavigationResolver`
- V1 uses reserved key prefixes plus a package-grouped fallback for page modules
- widgets and actions never become automatic menu entries
- the current foundation DTO remains unchanged in V1

## Implementation Consequences

Before implementation starts, the package plan should assume the following:

- `OpsPanelProvider` configures Filament with the guard returned by `OpsGuardResolver`
- host integration documentation must include a `FilamentUser` example for the ops panel
- panel access tests must cover `canAccessPanel()` behavior for the ops panel
- navigation tests must cover reserved prefixes and the package-grouped fallback path
- no foundation DTO expansion is needed for the first implementation pass

## Status

These decisions have been carried back into the primary `003-*` documents.

This file should now be treated as archival planning history rather than the current canonical architecture source.
