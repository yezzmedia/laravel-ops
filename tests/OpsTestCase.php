<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Tests;

use Filament\FilamentServiceProvider;
use Illuminate\Support\Facades\Gate;
use Livewire\LivewireServiceProvider;
use YezzMedia\Foundation\Testing\FoundationTestCase;
use YezzMedia\Ops\OpsPanelProvider;
use YezzMedia\Ops\OpsServiceProvider;
use YezzMedia\Ops\Tests\Fixtures\TestOpsUser;

/**
 * Provides a realistic Testbench baseline for ops package tests.
 */
abstract class OpsTestCase extends FoundationTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            OpsServiceProvider::class,
            OpsPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => TestOpsUser::class,
        ]);

        $app->booted(function (): void {
            foreach ([
                'viewOpsPanel',
                'ops.panel.access',
                'ops.dashboard.view',
                'ops.packages.view',
                'ops.features.view',
                'ops.diagnostics.view',
                'ops.runtime.view',
                'ops.audit.view',
                'ops.access.view',
                'ops.access.manage',
            ] as $ability) {
                Gate::define($ability, static fn (TestOpsUser $user): bool => $user->allows($ability));
            }
        });
    }
}
