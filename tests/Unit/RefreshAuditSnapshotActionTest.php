<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use YezzMedia\Ops\Actions\RefreshAuditSnapshotAction;
use YezzMedia\Ops\Contracts\OpsAuditWriter;
use YezzMedia\Ops\Data\OpsRecentActivityItem;
use YezzMedia\Ops\Support\ActivitylogRecentActivityReader;
use YezzMedia\Ops\Support\OpsRecentActivityResolver;
use YezzMedia\Ops\Tests\Fixtures\TestOpsUser;

it('refreshes the audit snapshot and writes an audit event', function (): void {
    auth()->guard('web')->setUser(TestOpsUser::fixture(['viewOpsPanel']));

    $writer = new class implements OpsAuditWriter
    {
        public array $last = [];

        public function write(string $eventKey, array $context = []): void
        {
            $this->last = compact('eventKey', 'context');
        }
    };

    $loggedAt = now()->toIso8601String();
    $cachedAt = now()->addSecond()->toIso8601String();

    config()->set('ops.integrations.audit.provider', stdClass::class);

    app()->instance(ActivitylogRecentActivityReader::class, new class($loggedAt, $cachedAt) extends ActivitylogRecentActivityReader
    {
        public function __construct(
            private readonly string $loggedAt,
            private readonly string $cachedAt,
        ) {}

        public function read(?int $limit = null): array
        {
            return [
                new OpsRecentActivityItem(
                    description: 'Recent audit entry',
                    event: 'updated',
                    logName: 'ops',
                    loggedAt: $this->loggedAt,
                    id: 'audit-1',
                    actorLabel: 'User #1',
                    subjectLabel: 'System #1',
                    contextPreview: 'key=value',
                    contextRows: [],
                    changesRows: [],
                ),
            ];
        }
    });

    app()->instance(OpsAuditWriter::class, $writer);
    app()->forgetInstance(OpsRecentActivityResolver::class);
    app()->forgetInstance(RefreshAuditSnapshotAction::class);

    $summary = app(RefreshAuditSnapshotAction::class)->run();

    expect($summary->status)->toBe('available')
        ->and($writer->last['eventKey'])->toBe('ops.audit.snapshot_refreshed')
        ->and($writer->last['context']['status'])->toBe('available')
        ->and($writer->last['context']['activity_count'])->toBe(1)
        ->and($writer->last['context']['backend'])->toBe('activitylog')
        ->and($writer->last['context']['operator_id'])->toBe(Auth::guard('web')->id());
});
