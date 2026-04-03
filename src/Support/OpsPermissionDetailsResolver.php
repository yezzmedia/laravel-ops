<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds a read-oriented drilldown for one declared permission.
 */
final class OpsPermissionDetailsResolver
{
    /**
     * @return array{
     *     summary: array{name: string, label: string, package: string, packageDescription: ?string, description: ?string, syncedLabel: string, syncedTone: string, roleHintsCount: int, assignedRoleCount: int},
     *     roleHints: list<string>,
     *     assignedRoles: list<string>
     * }
     */
    public function resolve(string $permission): array
    {
        $overview = app(OpsAccessBridge::class)->permissionOverview();

        $item = collect($overview['permissions'])
            ->first(static fn (array $record): bool => $record['name'] === $permission);

        if (! is_array($item)) {
            throw new NotFoundHttpException(sprintf('Permission [%s] is not available in the current permissions snapshot.', $permission));
        }

        $synced = (bool) ($item['synced'] ?? false);

        return [
            'summary' => [
                'name' => $item['name'],
                'label' => $item['label'],
                'package' => $item['package'],
                'packageDescription' => $item['packageDescription'] ?? null,
                'description' => $item['description'] ?? null,
                'syncedLabel' => $synced ? 'Synced' : 'Not synced',
                'syncedTone' => $synced ? 'success' : 'warning',
                'roleHintsCount' => (int) ($item['roleHintsCount'] ?? count($item['roleHints'] ?? [])),
                'assignedRoleCount' => (int) ($item['assignedRoleCount'] ?? count($item['assignedRoles'] ?? [])),
            ],
            'roleHints' => $item['roleHints'] ?? [],
            'roleHintsLabel' => $this->labels($item['roleHints'] ?? []),
            'assignedRoles' => $item['assignedRoles'] ?? [],
            'assignedRolesLabel' => $this->labels($item['assignedRoles'] ?? []),
        ];
    }

    /**
     * @param  array<int, string>  $values
     */
    private function labels(array $values): string
    {
        return $values === [] ? 'n/a' : implode(', ', $values);
    }
}
