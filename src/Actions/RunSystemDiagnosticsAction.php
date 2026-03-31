<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Throwable;
use YezzMedia\Ops\Data\OpsDiagnosticsSummary;
use YezzMedia\Ops\Events\SystemDiagnosticsRefreshed;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;
use YezzMedia\Ops\Support\OpsDiagnosticsCacheManager;
use YezzMedia\Ops\Support\OpsDiagnosticsSummaryResolver;
use YezzMedia\Ops\Support\OpsGuardResolver;

/**
 * Triggers one explicit diagnostics refresh attempt for the current operator scope.
 */
final class RunSystemDiagnosticsAction
{
    public function __construct(
        private readonly OpsAuthorizationResolver $authorization,
        private readonly OpsDiagnosticsCacheManager $cache,
        private readonly OpsDiagnosticsSummaryResolver $summaries,
        private readonly OpsGuardResolver $guards,
    ) {}

    public function run(): void
    {
        $this->authorization->authorizeSurface('diagnostics');

        $operatorKey = $this->operatorKey();

        if (! $this->cache->acquireLock($operatorKey)) {
            $this->dispatch($this->latestOrFallback('failed'));

            return;
        }

        try {
            if (! $this->cache->acquireCooldown($operatorKey)) {
                $this->dispatch($this->latestOrFallback('failed'));

                return;
            }

            $summary = $this->summaries->collect();

            $this->cache->invalidateDiagnosticsViewCaches();
            $this->cache->storeLatestSummary($summary);
            $this->recordOperatorActivity($summary);
            $this->dispatch($summary);
        } catch (Throwable) {
            $this->dispatch($this->latestOrFallback('failed'));
        } finally {
            $this->cache->releaseLock($operatorKey);
        }
    }

    private function latestOrFallback(string $status): OpsDiagnosticsSummary
    {
        return $this->cache->latestSummary() ?? $this->summaries->fallback($status);
    }

    private function dispatch(OpsDiagnosticsSummary $summary): void
    {
        event(new SystemDiagnosticsRefreshed(
            status: $summary->status,
            failingCount: $summary->failingCount,
            warningCount: $summary->warningCount,
            completedAt: $summary->completedAt,
        ));
    }

    private function operatorKey(): string
    {
        $guard = $this->guards->resolve()['guard'];
        $operator = Auth::guard($guard)->user();

        return $operator instanceof Authenticatable && $operator->getAuthIdentifier() !== null
            ? (string) $operator->getAuthIdentifier()
            : 'guest';
    }

    private function recordOperatorActivity(OpsDiagnosticsSummary $summary): void
    {
        if (! $summary->auditInstalled || ! function_exists('activity')) {
            return;
        }

        $guard = $this->guards->resolve()['guard'];
        $operator = Auth::guard($guard)->user();
        $activity = activity()
            ->useLog('ops')
            ->event('diagnostics_refresh')
            ->withProperties([
                'status' => $summary->status,
                'failing_count' => $summary->failingCount,
                'warning_count' => $summary->warningCount,
            ]);

        if ($operator instanceof Authenticatable) {
            $activity->causedBy($operator);
        }

        $activity->log('Ops diagnostics were refreshed.');
    }
}
