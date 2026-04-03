<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Spatie\Activitylog\Support\ActivityLogger;
use YezzMedia\Ops\Contracts\OpsAuditWriter;

final class ActivityLogOpsAuditWriter implements OpsAuditWriter
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function write(string $eventKey, array $context = []): void
    {
        $this->logger
            ->useLog('ops')
            ->event($eventKey)
            ->withProperties($context)
            ->log($eventKey);
    }
}
