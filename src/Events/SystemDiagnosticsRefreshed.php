<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Events;

/**
 * Carries operator-facing completion feedback for diagnostics refresh attempts.
 */
final readonly class SystemDiagnosticsRefreshed
{
    public function __construct(
        public string $status,
        public int $failingCount,
        public int $warningCount,
        public string $completedAt,
    ) {}
}
