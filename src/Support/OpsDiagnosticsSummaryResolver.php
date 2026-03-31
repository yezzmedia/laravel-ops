<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorManager;
use YezzMedia\Ops\Data\OpsDiagnosticsSummary;

/**
 * Aggregates foundation doctor output into one diagnostics summary for ops.
 */
final class OpsDiagnosticsSummaryResolver
{
    public function __construct(
        private readonly DoctorManager $doctor,
        private readonly OpsIntegrationResolver $integrations,
    ) {}

    public function collect(string $status = 'completed'): OpsDiagnosticsSummary
    {
        $results = $this->doctor->run();
        $integrationState = $this->integrations->resolve();

        return new OpsDiagnosticsSummary(
            status: $status,
            failingCount: $results->where('status', 'failed')->count(),
            warningCount: $results->where('status', 'warning')->count(),
            passedCount: $results->where('status', 'passed')->count(),
            skippedCount: $results->where('status', 'skipped')->count(),
            completedAt: now()->toIso8601String(),
            accessMode: $integrationState->accessMode,
            healthInstalled: $integrationState->healthInstalled,
            auditInstalled: $integrationState->auditInstalled,
            checks: $results
                ->map(static fn (DoctorResult $result): array => [
                    'key' => $result->key,
                    'package' => $result->package,
                    'status' => $result->status,
                    'message' => $result->message,
                    'isBlocking' => $result->isBlocking,
                ])
                ->all(),
        );
    }

    public function fallback(string $status): OpsDiagnosticsSummary
    {
        $integrationState = $this->integrations->resolve();

        return new OpsDiagnosticsSummary(
            status: $status,
            failingCount: 0,
            warningCount: 0,
            passedCount: 0,
            skippedCount: 0,
            completedAt: now()->toIso8601String(),
            accessMode: $integrationState->accessMode,
            healthInstalled: $integrationState->healthInstalled,
            auditInstalled: $integrationState->auditInstalled,
            checks: [],
        );
    }
}
