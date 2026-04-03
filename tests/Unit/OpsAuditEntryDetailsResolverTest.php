<?php

declare(strict_types=1);

use YezzMedia\Ops\Data\OpsRecentActivityItem;
use YezzMedia\Ops\Data\OpsRecentActivitySummary;
use YezzMedia\Ops\Support\OpsAuditEntryDetailsResolver;
use YezzMedia\Ops\Support\OpsRecentActivityCacheManager;

it('builds audit entry details from the recent activity snapshot', function (): void {
    app(OpsRecentActivityCacheManager::class)->store(new OpsRecentActivitySummary(
        status: 'available',
        backend: 'activitylog',
        activityCount: 1,
        latestDescription: 'Permissions synchronized.',
        latestAt: '2026-04-03T12:00:00+00:00',
        items: [
            new OpsRecentActivityItem(
                description: 'Permissions synchronized.',
                event: 'updated',
                logName: 'access',
                loggedAt: '2026-04-03T12:00:00+00:00',
                id: 'entry-1',
                actorLabel: 'User #1',
                subjectLabel: 'Role #2',
                contextPreview: 'role=super-admin',
                contextRows: [
                    ['key' => 'role', 'valuePreview' => 'super-admin', 'valueRaw' => 'super-admin'],
                ],
                changesRows: [
                    ['field' => 'name', 'oldPreview' => 'auditor', 'oldRaw' => 'auditor', 'newPreview' => 'super-admin', 'newRaw' => 'super-admin'],
                ],
            ),
        ],
    ));

    $details = app(OpsAuditEntryDetailsResolver::class)->resolve('entry-1');

    expect($details['summary'])->toMatchArray([
        'id' => 'entry-1',
        'description' => 'Permissions synchronized.',
        'event' => 'updated',
        'logName' => 'access',
        'actorLabel' => 'User #1',
        'subjectLabel' => 'Role #2',
        'backend' => 'activitylog',
        'statusLabel' => 'Available',
        'statusTone' => 'success',
        'sourceLabel' => 'cache',
    ])
        ->and($details['contextRows'])->toContain([
            'key' => 'role',
            'valuePreview' => 'super-admin',
            'valueRaw' => 'super-admin',
        ])
        ->and($details['changesRows'])->toContain([
            'field' => 'name',
            'oldPreview' => 'auditor',
            'oldRaw' => 'auditor',
            'newPreview' => 'super-admin',
            'newRaw' => 'super-admin',
        ]);
});
