<?php

declare(strict_types=1);

use YezzMedia\Ops\Support\OpsGuardResolver;

it('prefers a dedicated ops guard when configured', function (): void {
    config()->set('auth.guards.ops', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    config()->set('ops.auth.guard', 'ops');

    expect(app(OpsGuardResolver::class)->resolve())->toBe([
        'guard' => 'ops',
        'mode' => 'dedicated',
    ]);
});

it('falls back to the host guard when no dedicated guard is configured', function (): void {
    expect(app(OpsGuardResolver::class)->resolve())->toBe([
        'guard' => 'web',
        'mode' => 'host_boundary',
    ]);
});

it('fails closed when a configured dedicated guard is missing', function (): void {
    config()->set('ops.auth.guard', 'missing');

    expect(fn () => app(OpsGuardResolver::class)->resolve())
        ->toThrow(InvalidArgumentException::class, 'Configured ops guard [missing] is not defined.');
});

it('fails closed when no safe host guard can be resolved', function (): void {
    config()->set('ops.auth.host_guard', 'missing');

    expect(fn () => app(OpsGuardResolver::class)->resolve())
        ->toThrow(InvalidArgumentException::class, 'Unable to resolve a safe ops guard configuration.');
});
