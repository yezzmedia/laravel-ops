<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Throwable;
use YezzMedia\Ops\Contracts\OpsAuditWriter;
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
        private readonly OpsAuditWriter $audit,
    ) {}

    public function run(): void
    {
        $this->authorization->authorizeSurface('diagnostics');

        $operatorKey = $this->operatorKey();

        if (! $this->cache->acquireLock($operatorKey)) {
            $summary = $this->latestOrFallback('failed');
            $this->recordFailedOperatorActivity($summary, 'lock');
            $this->dispatch($summary);

            return;
        }

        try {
            if (! $this->cache->acquireCooldown($operatorKey)) {
                $summary = $this->latestOrFallback('failed');
                $this->recordFailedOperatorActivity($summary, 'cooldown');
                $this->dispatch($summary);

                return;
            }

            $summary = $this->summaries->collect();

            $this->cache->invalidateDiagnosticsViewCaches();
            $this->cache->storeLatestSummary($summary);
            $this->recordSuccessfulOperatorActivity($summary);
            $this->dispatch($summary);
        } catch (Throwable) {
            $summary = $this->latestOrFallback('failed');
            $this->recordFailedOperatorActivity($summary, 'exception');
            $this->dispatch($summary);
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

    private function recordSuccessfulOperatorActivity(OpsDiagnosticsSummary $summary): void
    {
        if (! $summary->auditInstalled) {
            return;
        }

        $this->audit->write('ops.diagnostics.refreshed', [
            'operator_id' => $this->operatorKey(),
            'status' => $summary->status,
            'failing_count' => $summary->failingCount,
            'warning_count' => $summary->warningCount,
            'completed_at' => $summary->completedAt,
        ]);
    }

    private function recordFailedOperatorActivity(OpsDiagnosticsSummary $summary, string $reason): void
    {
        if (! $summary->auditInstalled) {
            return;
        }

        $this->audit->write('ops.diagnostics.refresh_failed', [
            'operator_id' => $this->operatorKey(),
            'status' => $summary->status,
            'reason' => $reason,
            'failing_count' => $summary->failingCount,
            'warning_count' => $summary->warningCount,
            'completed_at' => $summary->completedAt,
        ]);
    }
}
