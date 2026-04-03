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
        public ?string $id = null,
        public string $actorLabel = 'System',
        public string $subjectLabel = 'Unknown subject',
        public ?string $contextPreview = null,
        /**
         * @var list<array{key: string, valuePreview: string, valueRaw: string}>
         */
        public array $contextRows = [],
        /**
         * @var list<array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string}>
         */
        public array $changesRows = [],
    ) {}
}
