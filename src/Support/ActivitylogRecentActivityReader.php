<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;
use YezzMedia\Ops\Data\OpsRecentActivityItem;

/**
 * Reads recent operator activity from Activitylog when that backend is installed.
 */
class ActivitylogRecentActivityReader
{
    /**
     * @return array<int, OpsRecentActivityItem>
     */
    public function read(int $limit = 5): array
    {
        $modelClass = config('ops.integrations.audit.model', 'Spatie\\Activitylog\\Models\\Activity');

        if (! is_string($modelClass) || $modelClass === '' || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new RuntimeException('Configured audit activity model is unavailable.');
        }

        /** @var class-string<Model> $modelClass */
        return $modelClass::query()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Model $activity): OpsRecentActivityItem {
                $createdAt = $activity->getAttribute('created_at');

                return new OpsRecentActivityItem(
                    description: $this->stringValue($activity->getAttribute('description'), fallback: 'Recent activity'),
                    event: $this->nullableStringValue($activity->getAttribute('event')),
                    logName: $this->nullableStringValue($activity->getAttribute('log_name')),
                    loggedAt: $createdAt instanceof Carbon ? $createdAt->toIso8601String() : $this->nullableStringValue($createdAt),
                );
            })
            ->all();
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function nullableStringValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
