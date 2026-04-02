<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds a read-oriented diagnostics drilldown for one doctor check.
 */
final class OpsDoctorCheckDetailsResolver
{
    public function __construct(
        private readonly OpsDiagnosticsSummaryResolver $summary,
    ) {}

    /**
     * @return array{
     *     summary: array{key: string, package: string, status: string, statusLabel: string, statusTone: string, message: string, isBlocking: bool, blockingLabel: string},
     *     snapshot: array{completedAt: string, accessMode: string, diagnosticsStatus: string, healthInstalled: bool, auditInstalled: bool},
     *     insights: array{missingPermissions: list<array{value: string}>, extraPermissions: list<array{value: string}>, declaredPermissionsCount: int|null, persistedPermissionsCount: int|null, roleName: ?string, exception: ?string, exceptionMessage: ?string},
     *     rawContextRows: list<array{key: string, valuePreview: string, valueRaw: string}>
     * }
     */
    public function resolve(string $package, string $check): array
    {
        $summary = $this->summary->collect();

        $record = collect($summary->checks)
            ->first(static fn (array $item): bool => $item['package'] === $package && $item['key'] === $check);

        if (! is_array($record)) {
            throw new NotFoundHttpException(sprintf('Doctor check [%s] for package [%s] is not available.', $check, $package));
        }

        $context = is_array($record['context'] ?? null) ? $record['context'] : [];
        $declaredPermissions = Arr::get($context, 'declared_permissions');
        $persistedPermissions = Arr::get($context, 'persisted_permissions');

        return [
            'summary' => [
                'key' => $record['key'],
                'package' => $record['package'],
                'status' => $record['status'],
                'statusLabel' => str($record['status'])->headline()->toString(),
                'statusTone' => $this->statusTone($record['status']),
                'message' => $record['message'],
                'isBlocking' => (bool) $record['isBlocking'],
                'blockingLabel' => $record['isBlocking'] ? 'Blocking' : 'Non-blocking',
            ],
            'snapshot' => [
                'completedAt' => $summary->completedAt,
                'accessMode' => str($summary->accessMode)->headline()->toString(),
                'diagnosticsStatus' => str($summary->status)->headline()->toString(),
                'healthInstalled' => $summary->healthInstalled,
                'auditInstalled' => $summary->auditInstalled,
            ],
            'insights' => [
                'missingPermissions' => $this->stringRows(Arr::get($context, 'missing_permissions')),
                'extraPermissions' => $this->stringRows(Arr::get($context, 'extra_permissions')),
                'declaredPermissionsCount' => is_array($declaredPermissions) ? count($declaredPermissions) : null,
                'persistedPermissionsCount' => is_array($persistedPermissions) ? count($persistedPermissions) : null,
                'roleName' => is_string(Arr::get($context, 'role_name')) ? Arr::get($context, 'role_name') : null,
                'exception' => is_string(Arr::get($context, 'exception')) ? Arr::get($context, 'exception') : null,
                'exceptionMessage' => is_string(Arr::get($context, 'message')) ? Arr::get($context, 'message') : null,
            ],
            'rawContextRows' => $this->contextRows($context),
        ];
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'failed' => 'danger',
            'warning' => 'warning',
            'passed' => 'success',
            default => 'gray',
        };
    }

    /**
     * @return list<array{value: string}>
     */
    private function stringRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $item): array => ['value' => (string) $item],
            $value,
        ));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{key: string, valuePreview: string, valueRaw: string}>
     */
    private function contextRows(array $context): array
    {
        return collect($context)
            ->map(
                fn (mixed $value, string $key): array => [
                    'key' => $key,
                    'valuePreview' => $this->previewValue($value),
                    'valueRaw' => $this->stringValue($value),
                ],
            )
            ->values()
            ->all();
    }

    private function previewValue(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            if ($this->isStringList($value)) {
                return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
            }

            return sprintf('Structured value (%d items)', count($value));
        }

        return $this->stringValue($value);
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isStringList(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    private function stringValue(mixed $value): string
    {
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);

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
}
