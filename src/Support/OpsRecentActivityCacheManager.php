<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Illuminate\Support\Facades\Cache;
use YezzMedia\Foundation\Support\CacheKeyFactory;
use YezzMedia\Ops\Data\OpsRecentActivitySummary;

/**
 * Owns recent-activity widget cache storage and invalidation.
 */
final class OpsRecentActivityCacheManager
{
    public function __construct(private readonly CacheKeyFactory $keys) {}

    public function summary(): ?OpsRecentActivitySummary
    {
        $summary = Cache::get($this->summaryKey());

        return $summary instanceof OpsRecentActivitySummary ? $summary : null;
    }

    public function store(OpsRecentActivitySummary $summary): void
    {
        Cache::put($this->summaryKey(), $summary, $this->ttlSeconds());
    }

    public function invalidate(): void
    {
        Cache::forget($this->summaryKey());
    }

    private function summaryKey(): string
    {
        return $this->keys->make('yezzmedia/laravel-ops', 'audit', 'recent_activity_summary');
    }

    private function ttlSeconds(): int
    {
        $seconds = config('ops.audit.recent_activity_widget_ttl_seconds', 30);

        return is_int($seconds) && $seconds > 0 ? $seconds : 30;
    }
}
