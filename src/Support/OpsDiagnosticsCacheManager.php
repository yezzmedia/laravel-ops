<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Illuminate\Support\Facades\Cache;
use YezzMedia\Foundation\Support\CacheKeyFactory;
use YezzMedia\Ops\Data\OpsDiagnosticsSummary;

/**
 * Owns deterministic diagnostics cache and lock keys for ops runtime flows.
 */
final class OpsDiagnosticsCacheManager
{
    public function __construct(private readonly CacheKeyFactory $keys) {}

    public function acquireLock(string $operatorKey): bool
    {
        return Cache::add($this->lockKey($operatorKey), true, $this->lockSeconds());
    }

    public function releaseLock(string $operatorKey): void
    {
        Cache::forget($this->lockKey($operatorKey));
    }

    public function acquireCooldown(string $operatorKey): bool
    {
        return Cache::add($this->cooldownKey($operatorKey), true, $this->cooldownSeconds());
    }

    public function storeLatestSummary(OpsDiagnosticsSummary $summary): void
    {
        Cache::put($this->latestSummaryKey(), $summary, $this->latestSummaryTtlSeconds());
    }

    public function storeFailingChecksSummary(OpsDiagnosticsSummary $summary): void
    {
        Cache::put($this->widgetKey('failing_checks'), $summary, $this->failingChecksWidgetTtlSeconds());
    }

    public function latestSummary(): ?OpsDiagnosticsSummary
    {
        $summary = Cache::get($this->latestSummaryKey());

        return $summary instanceof OpsDiagnosticsSummary ? $summary : null;
    }

    public function failingChecksSummary(): ?OpsDiagnosticsSummary
    {
        $summary = Cache::get($this->widgetKey('failing_checks'));

        return $summary instanceof OpsDiagnosticsSummary ? $summary : null;
    }

    public function invalidateDiagnosticsViewCaches(): void
    {
        Cache::forget($this->pageKey('system_health'));
        Cache::forget($this->widgetKey('failing_checks'));
    }

    public function pageKey(string $page): string
    {
        return $this->keys->make('yezzmedia/laravel-ops', 'diagnostics', 'page', [$page]);
    }

    public function widgetKey(string $widget): string
    {
        return $this->keys->make('yezzmedia/laravel-ops', 'diagnostics', 'widget', [$widget]);
    }

    private function latestSummaryKey(): string
    {
        return $this->keys->make('yezzmedia/laravel-ops', 'diagnostics', 'latest_summary');
    }

    private function lockKey(string $operatorKey): string
    {
        return $this->keys->make('yezzmedia/laravel-ops', 'diagnostics', 'lock', [$operatorKey]);
    }

    private function cooldownKey(string $operatorKey): string
    {
        return $this->keys->make('yezzmedia/laravel-ops', 'diagnostics', 'cooldown', [$operatorKey]);
    }

    private function cooldownSeconds(): int
    {
        $seconds = config('ops.diagnostics.cooldown_seconds', 30);

        return is_int($seconds) && $seconds > 0 ? $seconds : 30;
    }

    private function lockSeconds(): int
    {
        $seconds = config('ops.diagnostics.lock_seconds', 30);

        return is_int($seconds) && $seconds > 0 ? $seconds : 30;
    }

    private function latestSummaryTtlSeconds(): int
    {
        $seconds = config('ops.diagnostics.latest_summary_ttl_seconds', 300);

        return is_int($seconds) && $seconds > 0 ? $seconds : 300;
    }

    private function failingChecksWidgetTtlSeconds(): int
    {
        $seconds = config('ops.diagnostics.failing_checks_widget_ttl_seconds', 30);

        return is_int($seconds) && $seconds > 0 ? $seconds : 30;
    }
}
