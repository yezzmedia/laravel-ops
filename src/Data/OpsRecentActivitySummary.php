<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Data;

/**
 * Represents a normalized recent-activity overview for ops consumers.
 */
final readonly class OpsRecentActivitySummary
{
    /**
     * @param  array<int, OpsRecentActivityItem>  $items
     */
    public function __construct(
        public string $status,
        public ?string $backend,
        public int $activityCount,
        public ?string $latestDescription,
        public ?string $latestAt,
        public array $items,
    ) {}
}
