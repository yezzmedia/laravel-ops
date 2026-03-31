<?php

declare(strict_types=1);

use Filament\Panel;
use Filament\PanelRegistry;
use YezzMedia\Access\AccessPlatformPackage;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;
use YezzMedia\Ops\Tests\Fixtures\TestOpsUser;

function opsPanel(): Panel
{
    $panel = app(PanelRegistry::class)->get('ops');

    if (! $panel instanceof Panel) {
        throw new RuntimeException('The ops panel is not registered.');
    }

    return $panel;
}

it('allows an authorized operator to reach the ops panel', function (): void {
    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    expect(app(OpsAuthorizationResolver::class)->canAccessPanel(opsPanel()))->toBeTrue();
});

it('forbids an authenticated operator without reduced-mode access', function (): void {
    $user = TestOpsUser::fixture([]);

    auth()->guard('web')->login($user);

    expect(app(OpsAuthorizationResolver::class)->canAccessPanel(opsPanel()))->toBeFalse();
});

it('requires the access-owned panel permission when access integration is active', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    $panel = opsPanel();

    expect(app(OpsAuthorizationResolver::class)->canAccessPanel($panel))->toBeFalse();

    $authorizedUser = TestOpsUser::fixture(['ops.panel.access']);

    auth()->guard('web')->login($authorizedUser);

    expect(app(OpsAuthorizationResolver::class)->canAccessPanel($panel))->toBeTrue();
});
