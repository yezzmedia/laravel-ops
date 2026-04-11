# Integrations And Audit Boundaries

## Diagnostics and health-provider context

- Keep diagnostics grounded in `DoctorManager` results.
- Treat optional host health-provider detection as contextual visibility only.
- Do not present the ops package as the owner of third-party health checks unless a real bridge is added and tested.

## Access integration

- Keep access-facing visibility routed through `OpsAccessBridge` and the access registries.
- Keep access mutations explicit, permission-gated, and localized to their package-owned workflows.
- Keep permission and role detail surfaces read-only unless the page intentionally owns a supported action.

## Audit integration

- Keep the audit writer selected through `ops.integrations.audit.provider` and related config.
- Keep recent activity reads separate from audit snapshot refresh actions.
- Keep listeners and cache invalidation tied to real configured audit events rather than custom polling logic.

## Security governance declarations

`laravel-ops` currently declares:

- security request `ops.request.auth.login-throttle`
- security requirement `ops.auth.login-throttle`

Keep those declarations aligned with the actual package posture and avoid inventing parallel security vocabularies.
