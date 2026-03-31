<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorManager;
use YezzMedia\Ops\Data\OpsDiagnosticsSummary;
use YezzMedia\Ops\Support\OpsDiagnosticsCacheManager;
use YezzMedia\Ops\Support\OpsDiagnosticsSummaryResolver;
use YezzMedia\Ops\Support\OpsFailingChecksWidgetDataResolver;

it('caches the failing checks widget summary for repeated reads', function (): void {
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
    app()->forgetInstance(OpsFailingChecksWidgetDataResolver::class);

    $resolver = app(OpsFailingChecksWidgetDataResolver::class);
    $first = $resolver->resolve();
    $second = $resolver->resolve();

    expect($first->failingCount)->toBe(1)
        ->and($first->warningCount)->toBe(1)
        ->and($second->failingCount)->toBe(1)
        ->and($second->warningCount)->toBe(1)
        ->and(app(OpsDiagnosticsCacheManager::class)->failingChecksSummary())->toBeInstanceOf(OpsDiagnosticsSummary::class);
});

it('reuses a fresh latest diagnostics summary before recollecting results', function (): void {
    $latestSummary = new OpsDiagnosticsSummary(
        status: 'completed',
        failingCount: 2,
        warningCount: 1,
        passedCount: 0,
        skippedCount: 0,
        completedAt: now()->toIso8601String(),
        accessMode: 'reduced',
        healthInstalled: false,
        auditInstalled: false,
        checks: [],
    );

    $manager = new class extends DoctorManager
    {
        public function __construct() {}

        public function run(): Collection
        {
            throw new RuntimeException('Doctor should not run when latest summary is still fresh.');
        }
    };

    app()->instance(DoctorManager::class, $manager);
    app()->forgetInstance(OpsDiagnosticsSummaryResolver::class);
    app()->forgetInstance(OpsFailingChecksWidgetDataResolver::class);

    $cache = app(OpsDiagnosticsCacheManager::class);
    $cache->storeLatestSummary($latestSummary);

    $summary = app(OpsFailingChecksWidgetDataResolver::class)->resolve();

    expect($summary->failingCount)->toBe(2)
        ->and($summary->warningCount)->toBe(1);
});
