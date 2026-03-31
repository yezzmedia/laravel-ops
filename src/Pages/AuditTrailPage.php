<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use YezzMedia\Ops\Data\OpsRecentActivityItem;
use YezzMedia\Ops\Support\OpsRecentActivityResolver;

/**
 * Surfaces recent operator-visible audit activity when a backend exists.
 */
final class AuditTrailPage extends OpsPage
{
    protected static string $opsSurface = 'audit';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Audit';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'audit';

    protected static ?string $title = 'Audit trail';

    protected string $view = 'ops::pages.audit-trail-page';

    /**
     * @var array{status: string, backend: ?string, activityCount: int, latestDescription: ?string, latestAt: ?string, items: list<OpsRecentActivityItem>}
     */
    public array $summary = [
        'status' => 'unavailable',
        'backend' => null,
        'activityCount' => 0,
        'latestDescription' => null,
        'latestAt' => null,
        'items' => [],
    ];

    public function mount(): void
    {
        $summary = app(OpsRecentActivityResolver::class)->resolve();

        $this->summary = [
            'status' => $summary->status,
            'backend' => $summary->backend,
            'activityCount' => $summary->activityCount,
            'latestDescription' => $summary->latestDescription,
            'latestAt' => $summary->latestAt,
            'items' => array_values($summary->items),
        ];
    }
}
