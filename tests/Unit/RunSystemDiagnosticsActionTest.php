<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorManager;
use YezzMedia\Ops\Actions\RunSystemDiagnosticsAction;
use YezzMedia\Ops\Data\OpsDiagnosticsSummary;
use YezzMedia\Ops\Events\SystemDiagnosticsRefreshed;
use YezzMedia\Ops\Support\OpsDiagnosticsCacheManager;
use YezzMedia\Ops\Support\OpsDiagnosticsSummaryResolver;
use YezzMedia\Ops\Tests\Fixtures\TestOpsUser;

it('dispatches a completed diagnostics event and refreshes diagnostics caches', function (): void {
    auth()->guard('web')->setUser(TestOpsUser::fixture(['viewOpsPanel']));

    $manager = new class(collect([new DoctorResult('ops.ready', 'yezzmedia/laravel-ops', 'warning', 'Needs review.', false), new DoctorResult('ops.cache', 'yezzmedia/laravel-ops', 'failed', 'Cache is down.', true)])) extends DoctorManager
    {
        /**
         * @param  Collection<int, DoctorResult>  $results
         */
        public function __construct(private readonly Collection $results) {}

        public function run(): Collection
        {
            return $this->results;
        }
    };

    app()->instance(DoctorManager::class, $manager);
    app()->forgetInstance(OpsDiagnosticsSummaryResolver::class);
    app()->forgetInstance(RunSystemDiagnosticsAction::class);

    $cache = app(OpsDiagnosticsCacheManager::class);
    cache()->put($cache->pageKey('system_health'), 'stale-page', 300);
    cache()->put($cache->widgetKey('failing_checks'), 'stale-widget', 300);

    Event::fake([SystemDiagnosticsRefreshed::class]);

    app(RunSystemDiagnosticsAction::class)->run();

    Event::assertDispatched(SystemDiagnosticsRefreshed::class, function (SystemDiagnosticsRefreshed $event): bool {
        return $event->status === 'completed'
            && $event->failingCount === 1
            && $event->warningCount === 1
            && $event->completedAt !== '';
    });

    $summary = $cache->latestSummary();

    expect($summary)->toBeInstanceOf(OpsDiagnosticsSummary::class)
        ->and($summary?->failingCount)->toBe(1)
        ->and($summary?->warningCount)->toBe(1)
        ->and(cache()->get($cache->pageKey('system_health')))->toBeNull()
        ->and(cache()->get($cache->widgetKey('failing_checks')))->toBeNull();
});

it('dispatches a failed diagnostics event when the refresh is already running', function (): void {
    auth()->guard('web')->setUser(TestOpsUser::fixture(['viewOpsPanel']));

    cache()->add('website:yezzmedia/laravel-ops:diagnostics:lock:1', true, 30);

    Event::fake([SystemDiagnosticsRefreshed::class]);

    app(RunSystemDiagnosticsAction::class)->run();

    Event::assertDispatched(SystemDiagnosticsRefreshed::class, function (SystemDiagnosticsRefreshed $event): bool {
        return $event->status === 'failed'
            && $event->failingCount === 0
            && $event->warningCount === 0;
    });
});

it('dispatches a failed diagnostics event when the refresh is rate limited', function (): void {
    auth()->guard('web')->setUser(TestOpsUser::fixture(['viewOpsPanel']));

    cache()->add('website:yezzmedia/laravel-ops:diagnostics:cooldown:1', true, 30);

    Event::fake([SystemDiagnosticsRefreshed::class]);

    app(RunSystemDiagnosticsAction::class)->run();

    Event::assertDispatched(SystemDiagnosticsRefreshed::class, function (SystemDiagnosticsRefreshed $event): bool {
        return $event->status === 'failed'
            && $event->failingCount === 0
            && $event->warningCount === 0;
    });
});

it('dispatches a failed diagnostics event when diagnostics collection throws', function (): void {
    auth()->guard('web')->setUser(TestOpsUser::fixture(['viewOpsPanel']));

    $manager = new class extends DoctorManager
    {
        public function __construct() {}

        public function run(): Collection
        {
            throw new RuntimeException('boom');
        }
    };

    app()->instance(DoctorManager::class, $manager);
    app()->forgetInstance(OpsDiagnosticsSummaryResolver::class);
    app()->forgetInstance(RunSystemDiagnosticsAction::class);

    Event::fake([SystemDiagnosticsRefreshed::class]);

    app(RunSystemDiagnosticsAction::class)->run();

    Event::assertDispatched(SystemDiagnosticsRefreshed::class, function (SystemDiagnosticsRefreshed $event): bool {
        return $event->status === 'failed'
            && $event->failingCount === 0
            && $event->warningCount === 0;
    });
});

it('rejects diagnostics refresh when the operator lacks access', function (): void {
    auth()->guard('web')->setUser(TestOpsUser::fixture([]));

    expect(fn () => app(RunSystemDiagnosticsAction::class)->run())
        ->toThrow(AuthorizationException::class, 'This operator cannot access the [diagnostics] surface.');
});
