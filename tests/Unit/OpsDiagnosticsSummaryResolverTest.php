<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use YezzMedia\Access\AccessPlatformPackage;
use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorManager;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Support\OpsDiagnosticsSummaryResolver;

it('normalizes doctor results into one ops diagnostics summary', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $manager = new class(collect([new DoctorResult('ops.ready', 'yezzmedia/laravel-ops', 'passed', 'Ready.', false), new DoctorResult('ops.health', 'yezzmedia/laravel-ops', 'warning', 'Needs review.', false), new DoctorResult('ops.cache', 'yezzmedia/laravel-ops', 'failed', 'Cache unavailable.', true), new DoctorResult('ops.skip', 'yezzmedia/laravel-ops', 'skipped', 'Skipped.', false)])) extends DoctorManager
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

    $summary = app(OpsDiagnosticsSummaryResolver::class)->collect();

    expect($summary->status)->toBe('completed')
        ->and($summary->failingCount)->toBe(1)
        ->and($summary->warningCount)->toBe(1)
        ->and($summary->passedCount)->toBe(1)
        ->and($summary->skippedCount)->toBe(1)
        ->and($summary->accessMode)->toBe('access_integrated')
        ->and($summary->healthInstalled)->toBeFalse()
        ->and($summary->auditInstalled)->toBeFalse()
        ->and($summary->checks)->toHaveCount(4)
        ->and($summary->checks[2])->toBe([
            'key' => 'ops.cache',
            'package' => 'yezzmedia/laravel-ops',
            'status' => 'failed',
            'message' => 'Cache unavailable.',
            'isBlocking' => true,
        ]);
});
