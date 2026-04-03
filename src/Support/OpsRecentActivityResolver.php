<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Illuminate\Support\Carbon;
use Throwable;
use YezzMedia\Ops\Data\OpsRecentActivitySummary;

/**
 * Normalizes the optional audit backend into a compact recent-activity summary.
 */
final class OpsRecentActivityResolver
{
    public function __construct(
        private readonly OpsIntegrationResolver $integrations,
        private readonly OpsRecentActivityCacheManager $cache,
        private readonly ActivitylogRecentActivityReader $activitylog,
    ) {}

    public function resolve(): OpsRecentActivitySummary
    {
        $cachedSummary = $this->cache->summary();

        if ($cachedSummary instanceof OpsRecentActivitySummary) {
            return new OpsRecentActivitySummary(
                status: $cachedSummary->status,
                backend: $cachedSummary->backend,
                activityCount: $cachedSummary->activityCount,
                latestDescription: $cachedSummary->latestDescription,
                latestAt: $cachedSummary->latestAt,
                items: $cachedSummary->items,
                cachedAt: $cachedSummary->cachedAt,
                source: 'cache',
            );
        }

        $integrationState = $this->integrations->resolve();

        if (! $integrationState->auditInstalled) {
            $summary = new OpsRecentActivitySummary(
                status: 'unavailable',
                backend: null,
                activityCount: 0,
                latestDescription: null,
                latestAt: null,
                items: [],
                cachedAt: Carbon::now()->toIso8601String(),
                source: 'fresh read',
            );

            $this->cache->store($summary);

            return $summary;
        }

        try {
            $items = $this->activitylog->read();

            $summary = new OpsRecentActivitySummary(
                status: $items === [] ? 'empty' : 'available',
                backend: 'activitylog',
                activityCount: count($items),
                latestDescription: $items[0]->description ?? null,
                latestAt: $items[0]->loggedAt ?? null,
                items: $items,
                cachedAt: Carbon::now()->toIso8601String(),
                source: 'fresh read',
            );
        } catch (Throwable) {
            $summary = new OpsRecentActivitySummary(
                status: 'degraded',
                backend: 'activitylog',
                activityCount: 0,
                latestDescription: null,
                latestAt: null,
                items: [],
                cachedAt: Carbon::now()->toIso8601String(),
                source: 'fresh read',
            );
        }

        $this->cache->store($summary);

        return $summary;
    }
}
