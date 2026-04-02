<?php

declare(strict_types=1);

use YezzMedia\Ops\Support\OpsFeatureOverviewResolver;

it('exposes the ops-owned feature set through the feature overview resolver', function (): void {
    $features = app(OpsFeatureOverviewResolver::class)->resolve();

    expect($features)->toHaveCount(5)
        ->and(array_column($features, 'name'))->toBe([
            'ops.audit',
            'ops.diagnostics',
            'ops.features',
            'ops.packages',
            'ops.runtime',
        ])
        ->and(array_column($features, 'label'))->toBe([
            'Audit visibility',
            'Diagnostics',
            'Feature visibility',
            'Package visibility',
            'Runtime posture',
        ])
        ->and(collect($features)->every(fn (array $feature): bool => $feature['package'] === 'yezzmedia/laravel-ops'))->toBeTrue();
});
