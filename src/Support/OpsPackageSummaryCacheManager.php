<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Illuminate\Support\Facades\Cache;
use YezzMedia\Foundation\Support\CacheKeyFactory;
use YezzMedia\Ops\Data\OpsPackageSummary;

/**
 * Owns package-summary cache storage for overview widgets.
 */
final class OpsPackageSummaryCacheManager
{
    public function __construct(private readonly CacheKeyFactory $keys) {}

    public function summary(): ?OpsPackageSummary
    {
        $summary = Cache::get($this->summaryKey());

        return $summary instanceof OpsPackageSummary ? $summary : null;
    }

    public function store(OpsPackageSummary $summary): void
    {
        Cache::put($this->summaryKey(), $summary, $this->ttlSeconds());
    }

    private function summaryKey(): string
    {
        return $this->keys->make('yezzmedia/laravel-ops', 'packages', 'installed_summary');
    }

    private function ttlSeconds(): int
    {
        $seconds = config('ops.packages.installed_widget_ttl_seconds', 300);

        return is_int($seconds) && $seconds > 0 ? $seconds : 300;
    }
}
