<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Carbon\Carbon;
use YezzMedia\Ops\Data\OpsDiagnosticsSummary;

/**
 * Resolves the compact failing-check summary used by the diagnostics dashboard widget.
 */
final class OpsFailingChecksWidgetDataResolver
{
    public function __construct(
        private readonly OpsDiagnosticsCacheManager $cache,
        private readonly OpsDiagnosticsSummaryResolver $summaries,
    ) {}

    public function resolve(): OpsDiagnosticsSummary
    {
        $cachedSummary = $this->cache->failingChecksSummary();

        if ($cachedSummary instanceof OpsDiagnosticsSummary) {
            return $cachedSummary;
        }

        $latestSummary = $this->cache->latestSummary();

        if ($latestSummary instanceof OpsDiagnosticsSummary && $this->summaryIsFresh($latestSummary)) {
            $this->cache->storeFailingChecksSummary($latestSummary);

            return $latestSummary;
        }

        try {
            $summary = $this->summaries->collect();
        } catch (\Throwable) {
            $summary = $this->summaries->fallback('failed');
        }

        $this->cache->storeFailingChecksSummary($summary);

        return $summary;
    }

    private function summaryIsFresh(OpsDiagnosticsSummary $summary): bool
    {
        return now()->diffInSeconds(Carbon::parse($summary->completedAt), absolute: true) <= $this->widgetTtlSeconds();
    }

    private function widgetTtlSeconds(): int
    {
        $seconds = config('ops.diagnostics.failing_checks_widget_ttl_seconds', 30);

        return is_int($seconds) && $seconds > 0 ? $seconds : 30;
    }
}
