<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Data;

/**
 * Represents one compact package-level overview for operator-facing widgets.
 */
final readonly class OpsPackageSummary
{
    public function __construct(
        public int $installedCount,
        public int $enabledCount,
        public int $disabledCount,
        public int $featurePackageCount,
        public string $status,
    ) {}
}
