<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use YezzMedia\Ops\Data\OpsRecentActivityItem;

/**
 * Builds a read-oriented audit drilldown for one recent activity entry.
 */
final class OpsAuditEntryDetailsResolver
{
    public function __construct(
        private readonly OpsRecentActivityResolver $summary,
    ) {}

    /**
     * @return array{
     *     summary: array{id: string, description: string, event: string, logName: string, loggedAt: string, actorLabel: string, subjectLabel: string, contextPreview: ?string, contextJson: string, changesJson: string, backend: string, statusLabel: string, statusTone: string, sourceLabel: string, cachedAt: ?string},
     *     contextRows: list<array{key: string, valuePreview: string, valueRaw: string}>,
     *     changesRows: list<array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string}>
     * }
     */
    public function resolve(string $entry): array
    {
        $summary = $this->summary->resolve();

        $item = collect($summary->items)
            ->first(fn (OpsRecentActivityItem $activity): bool => $this->entryId($activity) === $entry);

        if (! $item instanceof OpsRecentActivityItem) {
            throw new NotFoundHttpException(sprintf('Audit entry [%s] is not available in the current recent-activity snapshot.', $entry));
        }

        return [
            'summary' => [
                'id' => $this->entryId($item),
                'description' => $item->description,
                'event' => $item->event ?? 'n/a',
                'logName' => $item->logName ?? 'n/a',
                'loggedAt' => $item->loggedAt ?? 'n/a',
                'actorLabel' => $item->actorLabel,
                'subjectLabel' => $item->subjectLabel,
                'contextPreview' => $item->contextPreview,
                'contextJson' => json_encode($item->contextRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'changesJson' => json_encode($item->changesRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'backend' => $summary->backend ?? 'Unavailable',
                'statusLabel' => str($summary->status)->headline()->toString(),
                'statusTone' => $this->statusTone($summary->status),
                'sourceLabel' => $summary->source ?? 'fresh read',
                'cachedAt' => $summary->cachedAt,
            ],
            'contextRows' => $item->contextRows,
            'changesRows' => $item->changesRows,
        ];
    }

    private function entryId(OpsRecentActivityItem $item): string
    {
        return $item->id ?? sha1(sprintf('%s|%s|%s|%s', $item->description, $item->event ?? '', $item->logName ?? '', $item->loggedAt ?? ''));
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'available' => 'success',
            'empty' => 'warning',
            'degraded' => 'danger',
            default => 'gray',
        };
    }
}
