<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds a read-oriented drilldown for one persisted role.
 */
final class OpsRoleDetailsResolver
{
    public function resolve(string $role): array
    {
        $overview = app(OpsAccessBridge::class)->managementOverview();

        $item = collect($overview['roles'])
            ->first(static fn (array $record): bool => $record['name'] === $role);

        if (! is_array($item)) {
            throw new NotFoundHttpException(sprintf('Role [%s] is not available in the current access snapshot.', $role));
        }

        $superAdminRole = (string) config('access.super_admin.role_name', 'super-admin');
        $isSuperAdminRole = $item['name'] === $superAdminRole;

        return [
            'summary' => [
                'name' => $item['name'],
                'permissionCount' => (int) $item['permissionCount'],
                'assignmentCount' => (int) $item['assignmentCount'],
                'isSuperAdminRole' => $isSuperAdminRole,
                'superAdminStatus' => $isSuperAdminRole ? 'Super-admin' : 'Standard',
            ],
            'permissionNames' => $item['permissionNames'] ?? [],
            'permissionNamesLabel' => $item['permissionNamesLabel'] ?? 'n/a',
        ];
    }
}
