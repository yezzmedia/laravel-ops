<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use YezzMedia\Ops\Data\OpsRecentActivityItem;
use YezzMedia\Ops\Data\OpsRecentActivitySummary;
use YezzMedia\Ops\Support\ActivitylogRecentActivityReader;
use YezzMedia\Ops\Support\OpsRecentActivityCacheManager;
use YezzMedia\Ops\Support\OpsRecentActivityResolver;

it('returns an unavailable summary when no supported audit backend exists', function (): void {
    config()->set('ops.integrations.audit.provider', null);
    app()->forgetInstance(OpsRecentActivityResolver::class);

    $summary = app(OpsRecentActivityResolver::class)->resolve();

    expect($summary)->toBeInstanceOf(OpsRecentActivitySummary::class)
        ->and($summary->status)->toBe('unavailable')
        ->and($summary->backend)->toBeNull()
        ->and($summary->activityCount)->toBe(0);
});

it('returns recent activity from the configured backend when available', function (): void {
    config()->set('ops.integrations.audit.provider', stdClass::class);
    app()->instance(ActivitylogRecentActivityReader::class, new class extends ActivitylogRecentActivityReader
    {
        public function read(?int $limit = null): array
        {
            return [
                new OpsRecentActivityItem('Permissions synchronized.', 'updated', 'ops', now()->toIso8601String(), actorLabel: 'User #1', subjectLabel: 'Role #1', contextPreview: 'role=super-admin', contextRows: [], changesRows: []),
                new OpsRecentActivityItem('Roles synchronized.', 'updated', 'ops', now()->subMinute()->toIso8601String(), actorLabel: 'User #1', subjectLabel: 'Role #2', contextPreview: null, contextRows: [], changesRows: []),
            ];
        }
    });
    app()->forgetInstance(OpsRecentActivityResolver::class);

    $summary = app(OpsRecentActivityResolver::class)->resolve();

    expect($summary->status)->toBe('available')
        ->and($summary->backend)->toBe('activitylog')
        ->and($summary->activityCount)->toBe(2)
        ->and($summary->latestDescription)->toBe('Permissions synchronized.')
        ->and($summary->source)->toBe('fresh read')
        ->and(app(OpsRecentActivityCacheManager::class)->summary())->toBeInstanceOf(OpsRecentActivitySummary::class);
});

it('returns a degraded summary when the activity backend cannot be read', function (): void {
    config()->set('ops.integrations.audit.provider', stdClass::class);
    app()->instance(ActivitylogRecentActivityReader::class, new class extends ActivitylogRecentActivityReader
    {
        public function read(?int $limit = null): array
        {
            throw new RuntimeException('boom');
        }
    });
    app()->forgetInstance(OpsRecentActivityResolver::class);

    $summary = app(OpsRecentActivityResolver::class)->resolve();

    expect($summary->status)->toBe('degraded')
        ->and($summary->backend)->toBe('activitylog')
        ->and($summary->activityCount)->toBe(0)
        ->and($summary->latestDescription)->toBeNull();
});

it('uses old_values and new_values from audit properties when attribute changes are not present', function (): void {
    $model = new class extends Model
    {
        protected $guarded = [];
    };

    $model->forceFill([
        'properties' => [
            'old_values' => [
                'address_line_1' => 'Old Street 1',
            ],
            'new_values' => [
                'address_line_1' => 'New Street 2',
            ],
        ],
    ]);

    $reader = new ActivitylogRecentActivityReader;
    $method = new ReflectionMethod(ActivitylogRecentActivityReader::class, 'changesRows');
    $method->setAccessible(true);

    expect($method->invoke($reader, $model))->toBe([
        [
            'field' => 'address_line_1',
            'oldPreview' => 'Old Street 1',
            'oldRaw' => 'Old Street 1',
            'newPreview' => 'New Street 2',
            'newRaw' => 'New Street 2',
        ],
    ]);
});
