<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Data;

/**
 * Represents one normalized diagnostics refresh summary for ops consumers.
 */
final readonly class OpsDiagnosticsSummary
{
    /**
     * @param  array<int, array{key: string, package: string, status: string, message: string, isBlocking: bool}>  $checks
     */
    public function __construct(
        public string $status,
        public int $failingCount,
        public int $warningCount,
        public int $passedCount,
        public int $skippedCount,
        public string $completedAt,
        public string $accessMode,
        public bool $healthInstalled,
        public bool $auditInstalled,
        public array $checks,
    ) {}
}
