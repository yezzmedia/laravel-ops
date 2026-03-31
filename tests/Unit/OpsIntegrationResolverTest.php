<?php

declare(strict_types=1);

use YezzMedia\Access\AccessPlatformPackage;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Support\OpsIntegrationResolver;

it('resolves reduced mode when access is not installed', function (): void {
    config()->set('ops.integrations.health.provider', 'Tests\\Fixtures\\MissingHealthProvider');
    config()->set('ops.integrations.audit.provider', 'Tests\\Fixtures\\MissingAuditProvider');

    $state = app(OpsIntegrationResolver::class)->resolve();

    expect($state->accessMode)->toBe('reduced')
        ->and($state->accessInstalled)->toBeFalse()
        ->and($state->healthInstalled)->toBeFalse()
        ->and($state->auditInstalled)->toBeFalse()
        ->and($state->reducedMode())->toBeTrue()
        ->and($state->showsAccessSurfaces())->toBeFalse()
        ->and($state->showsAccessMutations())->toBeFalse();
});

it('resolves access-integrated mode and optional backend availability', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);
    config()->set('ops.integrations.health.provider', stdClass::class);
    config()->set('ops.integrations.audit.provider', stdClass::class);

    $state = app(OpsIntegrationResolver::class)->resolve();

    expect($state->accessMode)->toBe('access_integrated')
        ->and($state->accessInstalled)->toBeTrue()
        ->and($state->healthInstalled)->toBeTrue()
        ->and($state->auditInstalled)->toBeTrue()
        ->and($state->accessIntegrated())->toBeTrue()
        ->and($state->showsAccessSurfaces())->toBeTrue()
        ->and($state->showsAccessMutations())->toBeTrue();
});
