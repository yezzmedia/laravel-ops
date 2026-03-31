<?php

declare(strict_types=1);

use Livewire\Livewire;
use YezzMedia\Access\AccessPlatformPackage;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Pages\AccessManagementPage;
use YezzMedia\Ops\Pages\AuditTrailPage;
use YezzMedia\Ops\Pages\FeaturesPage;
use YezzMedia\Ops\Pages\PackagesPage;
use YezzMedia\Ops\Pages\PermissionsPage;
use YezzMedia\Ops\Pages\SystemHealthPage;
use YezzMedia\Ops\Tests\Fixtures\TestOpsUser;

it('loads the read-oriented ops pages in reduced mode', function (): void {
    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    Livewire::test(PackagesPage::class)->assertOk();
    Livewire::test(FeaturesPage::class)->assertOk();
    Livewire::test(SystemHealthPage::class)->assertOk();
    Livewire::test(AuditTrailPage::class)->assertOk();
});

it('forbids access pages in reduced mode', function (): void {
    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    expect(PermissionsPage::shouldRegisterNavigation())->toBeFalse()
        ->and(PermissionsPage::canAccess())->toBeFalse()
        ->and(AccessManagementPage::shouldRegisterNavigation())->toBeFalse()
        ->and(AccessManagementPage::canAccess())->toBeFalse();
});

it('loads the access pages when access integration is active and the operator has explicit permissions', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $user = TestOpsUser::fixture([
        'ops.panel.access',
        'ops.access.view',
        'ops.access.manage',
    ]);

    auth()->guard('web')->login($user);

    expect(PermissionsPage::shouldRegisterNavigation())->toBeTrue()
        ->and(PermissionsPage::canAccess())->toBeTrue()
        ->and(AccessManagementPage::shouldRegisterNavigation())->toBeTrue()
        ->and(AccessManagementPage::canAccess())->toBeTrue();

    Livewire::test(PermissionsPage::class)->assertOk();
    Livewire::test(AccessManagementPage::class)->assertOk();
});
