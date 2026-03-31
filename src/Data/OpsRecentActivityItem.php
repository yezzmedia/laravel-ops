<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Data;

/**
 * Represents one compact recent activity entry for operator-facing summaries.
 */
final readonly class OpsRecentActivityItem
{
    public function __construct(
        public string $description,
        public ?string $event,
        public ?string $logName,
        public ?string $loggedAt,
    ) {}
}
