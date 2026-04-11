---
name: ops-package-development
description: "Build and maintain yezzmedia/laravel-ops. Activate when changing ops panel pages, ops navigation projection, diagnostics summaries, package or feature overviews, access or audit bridges, panel authorization, recent activity integration, or package tests that depend on the approved ops V1 surface."
license: MIT
metadata:
  author: yezzmedia
---

# Ops Package Development

## Documentation

Use `search-docs` for Laravel, Filament, Pest, Package Tools, and Boost details. Use the reference files in this skill for the approved ops package surface and current runtime boundaries.

Use the `foundation-package-development` skill when the work changes descriptor capability choices or other foundation registration mechanics.

## When To Use This Skill

Activate this skill when working inside `yezzmedia/laravel-ops`, especially when changing one of these areas:

- the Filament ops panel shell, ops panel authorization, or guard resolution
- first-party ops pages, widgets, and their operator-facing visibility rules
- projection of packages, features, permissions, ops modules, or runtime posture into ops summaries
- diagnostics aggregation, recent activity reads, or audit snapshot refresh flows
- access or audit integration bridges
- built-in ops feature declarations, audit events, or security governance entries
- package tests that prove the real ops registry and panel behavior

## Functional Workflow

1. Identify whether the change belongs to panel wiring, registry projection, diagnostics, access bridging, or audit visibility before editing code.
2. Read the matching reference file before changing public package surface.
3. Keep panel pages thin and keep aggregation logic in the resolver layer.
4. Keep first-party ops declarations aligned with what the panel currently exposes through pages, widgets, and optional companion plugins.
5. Verify resolver, page, and registration behavior with package tests that exercise the real package bootstrap path.

## Core Rules

- Keep `OpsPlatformPackage` declarative and aligned with the real panel surface.
- Keep `OpsServiceProvider` focused on bindings in `packageRegistered()` and descriptor registration in `packageBooted()`.
- Keep panel shell concerns inside `OpsPanelProvider`; do not move provider bootstrap into unrelated services.
- Keep `OpsNavigationResolver` as the single projection point for ops-module visibility.
- Keep package and feature overview resolvers derived from registries and navigation projection rather than duplicated page lists.
- Keep diagnostics rooted in foundation doctor results; treat optional host health providers as contextual integration only.
- Keep audit integration optional and driven by configured provider classes.
- Keep access visibility read-only unless the page explicitly owns a package-approved mutation flow.

## Testing Pattern

- Prefer package tests that exercise the real provider, panel provider, and registry path.
- Cover resolver behavior with focused unit tests when a change affects projection rules or summary shaping.
- Cover page and widget behavior with feature tests when user-visible headings, sections, or filters change.
- Keep authorization and plugin-loading checks in package tests instead of relying on host-only confirmation.

## References

- Use [references/runtime-surface.md](references/runtime-surface.md) for the approved ops runtime and declaration surface.
- Use [references/panel-and-navigation.md](references/panel-and-navigation.md) for panel pages, widget ownership, and navigation projection rules.
- Use [references/integrations-and-audit.md](references/integrations-and-audit.md) for diagnostics, access, audit, and optional provider integration boundaries.
- Use [references/testing.md](references/testing.md) for package verification expectations.
- Use [references/checklist.md](references/checklist.md) before finalizing ops changes.

## Common Pitfalls

- adding panel pages without aligning `OpsPlatformPackage` feature declarations and navigation expectations
- bypassing `OpsNavigationResolver` with hard-coded page lists inside summary resolvers
- describing optional host health providers as if the package owns their health checks directly
- mixing audit snapshot reads, recent activity projection, and audit writer responsibilities
- adding access mutations without keeping permission hints and authorization checks explicit
- proving behavior only in the host app instead of package tests
