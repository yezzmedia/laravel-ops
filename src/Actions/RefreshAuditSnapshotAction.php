<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Actions;

use Illuminate\Support\Facades\Auth;
use YezzMedia\Ops\Contracts\OpsAuditWriter;
use YezzMedia\Ops\Data\OpsRecentActivitySummary;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;
use YezzMedia\Ops\Support\OpsRecentActivityCacheManager;
use YezzMedia\Ops\Support\OpsRecentActivityResolver;

final class RefreshAuditSnapshotAction
{
    public function __construct(
        private readonly OpsAuthorizationResolver $authorization,
        private readonly OpsRecentActivityCacheManager $cache,
        private readonly OpsRecentActivityResolver $summary,
        private readonly OpsAuditWriter $audit,
    ) {}

    public function run(): OpsRecentActivitySummary
    {
        $this->authorization->authorizeSurface('audit');

        $this->cache->invalidate();

        $summary = $this->summary->resolve();

        $this->audit->write('ops.audit.snapshot_refreshed', [
            'backend' => $summary->backend,
            'status' => $summary->status,
            'activity_count' => $summary->activityCount,
            'cached_at' => $summary->cachedAt,
            'source' => $summary->source,
            'operator_id' => Auth::guard((string) config('ops.auth.guard', config('ops.auth.host_guard', 'web')))->id(),
        ]);

        return $summary;
    }
}
