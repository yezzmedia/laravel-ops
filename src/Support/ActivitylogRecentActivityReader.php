<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use JsonException;
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
    public function read(?int $limit = null): array
    {
        $modelClass = config('ops.integrations.audit.model', 'Spatie\\Activitylog\\Models\\Activity');

        if (! is_string($modelClass) || $modelClass === '' || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new RuntimeException('Configured audit activity model is unavailable.');
        }

        /** @var class-string<Model> $modelClass */
        $query = $modelClass::query()->latest();

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query
            ->get()
            ->map(function (Model $activity): OpsRecentActivityItem {
                $createdAt = $activity->getAttribute('created_at');

                return new OpsRecentActivityItem(
                    description: $this->stringValue($activity->getAttribute('description'), fallback: 'Recent activity'),
                    event: $this->nullableStringValue($activity->getAttribute('event')),
                    logName: $this->nullableStringValue($activity->getAttribute('log_name')),
                    loggedAt: $createdAt instanceof Carbon ? $createdAt->toIso8601String() : $this->nullableStringValue($createdAt),
                    id: $this->nullableStringValue($activity->getAttribute('id')),
                    actorLabel: $this->actorLabel($activity),
                    subjectLabel: $this->subjectLabel($activity),
                    contextPreview: $this->contextPreview($activity),
                    contextRows: $this->contextRows($activity),
                    changesRows: $this->changesRows($activity),
                );
            })
            ->all();
    }

    private function actorLabel(Model $activity): string
    {
        $type = $this->nullableStringValue($activity->getAttribute('causer_type'));
        $id = $this->nullableStringValue($activity->getAttribute('causer_id'));

        if ($type === null && $id === null) {
            return 'System';
        }

        return trim(sprintf('%s%s', $this->modelLabel($type), $id !== null ? sprintf(' #%s', $id) : ''));
    }

    private function subjectLabel(Model $activity): string
    {
        $type = $this->nullableStringValue($activity->getAttribute('subject_type'));
        $id = $this->nullableStringValue($activity->getAttribute('subject_id'));

        if ($type === null && $id === null) {
            return 'Unknown subject';
        }

        return trim(sprintf('%s%s', $this->modelLabel($type), $id !== null ? sprintf(' #%s', $id) : ''));
    }

    private function modelLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return 'Model';
        }

        return str($type)->afterLast('\\')->headline()->toString();
    }

    private function contextPreview(Model $activity): ?string
    {
        $values = $this->arrayValue($activity->getAttribute('properties'));

        if ($values === null || $values === []) {
            return null;
        }

        $preview = collect($values)
            ->map(fn (mixed $value, string $key): string => sprintf('%s=%s', $key, $this->previewValue($value)))
            ->take(2)
            ->implode(', ');

        return $preview !== '' ? $preview : null;
    }

    /**
     * @return list<array{key: string, valuePreview: string, valueRaw: string}>
     */
    private function contextRows(Model $activity): array
    {
        $properties = $this->arrayValue($activity->getAttribute('properties'));

        if ($properties === null) {
            return [];
        }

        return collect($properties)
            ->map(fn (mixed $value, string $key): array => [
                'key' => $key,
                'valuePreview' => $this->previewValue($value),
                'valueRaw' => $this->rawValue($value),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string}>
     */
    private function changesRows(Model $activity): array
    {
        $changes = $this->arrayValue($activity->getAttribute('attribute_changes'));

        if ($changes === null) {
            return [];
        }

        $newAttributes = $this->arrayValue($changes['attributes'] ?? null) ?? [];
        $oldAttributes = $this->arrayValue($changes['old'] ?? null) ?? [];

        if ($newAttributes === [] && $oldAttributes === []) {
            return [];
        }

        return collect(array_unique([...array_keys($newAttributes), ...array_keys($oldAttributes)]))
            ->map(function (string $field) use ($newAttributes, $oldAttributes): array {
                $oldValue = $oldAttributes[$field] ?? null;
                $newValue = $newAttributes[$field] ?? null;

                return [
                    'field' => $field,
                    'oldPreview' => $this->previewValue($oldValue),
                    'oldRaw' => $this->rawValue($oldValue),
                    'newPreview' => $this->previewValue($newValue),
                    'newRaw' => $this->rawValue($newValue),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function arrayValue(mixed $value): ?array
    {
        if ($value instanceof Collection) {
            return $value->all();
        }

        if (is_array($value)) {
            return $value;
        }

        return null;
    }

    private function previewValue(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            if (array_is_list($value)) {
                return sprintf('%d item(s)', count($value));
            }

            return sprintf('Structured value (%d item(s))', count($value));
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : 'null';
    }

    private function rawValue(mixed $value): string
    {
        if (is_array($value)) {
            try {
                $encoded = json_encode($value, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return '[]';
            }

            return is_string($encoded) ? $encoded : '[]';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
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
