<?php

declare(strict_types=1);

use Filament\PanelRegistry;
use Illuminate\Support\Facades\Auth;
use YezzMedia\Access\AccessPlatformPackage;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;
use YezzMedia\Ops\Support\OpsSurfaceVisibilityResolver;
use YezzMedia\Ops\Tests\Fixtures\TestOpsUser;

it('requires FilamentUser panel access and ops panel permission in access-integrated mode', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $panel = app(PanelRegistry::class)->get('ops') ?? throw new RuntimeException('Ops panel is not registered.');
    $user = TestOpsUser::fixture(['ops.panel.access', 'ops.diagnostics.view']);

    expect(app(OpsAuthorizationResolver::class)->canAccessPanel($panel, $user))->toBeTrue()
        ->and(app(OpsAuthorizationResolver::class)->canAccessSurface('diagnostics', $user))->toBeTrue()
        ->and(app(OpsAuthorizationResolver::class)->canAccessSurface('permissions', $user))->toBeFalse();
});

it('fails panel access when canAccessPanel is false even with permissions', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $panel = app(PanelRegistry::class)->get('ops') ?? throw new RuntimeException('Ops panel is not registered.');
    $user = TestOpsUser::fixture(['ops.panel.access', 'ops.diagnostics.view'], panelAccess: false);

    expect(app(OpsAuthorizationResolver::class)->canAccessPanel($panel, $user))->toBeFalse();
});

it('uses the host-owned reduced mode gate when access is absent', function (): void {
    $panel = app(PanelRegistry::class)->get('ops') ?? throw new RuntimeException('Ops panel is not registered.');
    $user = TestOpsUser::fixture(['viewOpsPanel']);

    expect(app(OpsAuthorizationResolver::class)->canAccessPanel($panel, $user))->toBeTrue()
        ->and(app(OpsAuthorizationResolver::class)->canAccessSurface('diagnostics', $user))->toBeTrue()
        ->and(app(OpsAuthorizationResolver::class)->canAccessSurface('permissions', $user))->toBeFalse();
});

it('resolves reduced-mode visibility separately from access-integrated visibility', function (): void {
    expect(app(OpsSurfaceVisibilityResolver::class)->visible('permissions'))->toBeFalse()
        ->and(app(OpsSurfaceVisibilityResolver::class)->visible('access_management'))->toBeFalse()
        ->and(app(OpsSurfaceVisibilityResolver::class)->visible('diagnostics'))->toBeTrue();

    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    expect(app(OpsSurfaceVisibilityResolver::class)->visible('permissions'))->toBeTrue()
        ->and(app(OpsSurfaceVisibilityResolver::class)->visible('access_management'))->toBeTrue();
});

it('authorizes the current authenticated user when no user is passed explicitly', function (): void {
    $user = TestOpsUser::fixture(['viewOpsPanel']);
    Auth::guard('web')->setUser($user);

    expect(app(OpsAuthorizationResolver::class)->canAccessSurface('diagnostics'))->toBeTrue();
});
