<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use YezzMedia\Foundation\Registry\PermissionRegistry;

/**
 * Bridges optional access runtime services into curated ops read and action flows.
 */
final class OpsAccessBridge
{
    private const PERMISSION_STORE_SETUP = 'YezzMedia\\Access\\Support\\PermissionStoreSetup';

    private const ROLE_MANAGER = 'YezzMedia\\Access\\Support\\RoleManager';

    private const USER_ROLE_MANAGER = 'YezzMedia\\Access\\Support\\UserRoleManager';

    private const SUPER_ADMIN_SAFETY_GUARD = 'YezzMedia\\Access\\Support\\SuperAdminSafetyGuard';

    public function __construct(
        private readonly PermissionRegistry $permissions,
        private readonly OpsIntegrationResolver $integrations,
    ) {}

    /**
     * @return array{
     *     installed: bool,
     *     available: bool,
     *     error: ?string,
     *     store: array{configPublished: bool, migrationsPublished: bool, pendingMigrations: bool, ready: bool},
     *     permissions: list<array{name: string, package: string, label: string, synced: bool, roleHints: list<string>, assignedRoles: list<string>}>,
     *     roles: list<array{name: string, permissionNames: list<string>}>
     * }
     */
    public function permissionOverview(): array
    {
        $store = [
            'configPublished' => false,
            'migrationsPublished' => false,
            'pendingMigrations' => false,
            'ready' => false,
        ];
        $available = false;
        $error = null;

        try {
            $storeSetup = $this->resolveService(self::PERMISSION_STORE_SETUP);

            if (! method_exists($storeSetup, 'configPublished') || ! method_exists($storeSetup, 'migrationsPublished') || ! method_exists($storeSetup, 'hasPendingPublishedMigrations') || ! method_exists($storeSetup, 'permissionStoreReady')) {
                throw new RuntimeException('The access permission store runtime is incomplete.');
            }

            $store = [
                'configPublished' => (bool) $storeSetup->configPublished(),
                'migrationsPublished' => (bool) $storeSetup->migrationsPublished(),
                'pendingMigrations' => (bool) $storeSetup->hasPendingPublishedMigrations(),
                'ready' => (bool) $storeSetup->permissionStoreReady(),
            ];
            $available = true;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $persistedPermissionNames = $available ? $this->persistedPermissionNames() : [];
        $roleRelationships = $available ? $this->roleRelationships() : [];

        return [
            'installed' => $this->integrations->resolve()->accessInstalled,
            'available' => $available,
            'error' => $error,
            'store' => $store,
            'permissions' => array_values($this->permissions->all()
                ->sortBy([
                    ['package', 'asc'],
                    ['name', 'asc'],
                ])
                ->map(function ($permission) use ($persistedPermissionNames, $roleRelationships): array {
                    $assignedRoles = array_keys(array_filter(
                        $roleRelationships,
                        static fn (array $permissionNames): bool => in_array($permission->name, $permissionNames, true),
                    ));

                    sort($assignedRoles);

                    return [
                        'name' => $permission->name,
                        'package' => $permission->package,
                        'label' => $permission->label,
                        'synced' => in_array($permission->name, $persistedPermissionNames, true),
                        'roleHints' => $this->normalizedNames($permission->defaultRoleHints ?? []),
                        'assignedRoles' => $assignedRoles,
                    ];
                })
                ->values()
                ->all()),
            'roles' => array_values(collect($roleRelationships)
                ->sortKeys()
                ->map(static fn (array $permissionNames, string $roleName): array => [
                    'name' => $roleName,
                    'permissionNames' => $permissionNames,
                ])
                ->values()
                ->all()),
        ];
    }

    /**
     * @return array{
     *     installed: bool,
     *     available: bool,
     *     error: ?string,
     *     superAdmin: array{enabled: bool, roleName: ?string, minimumOperators: int, operatorCount: int},
     *     roles: list<array{name: string, permissionCount: int, assignmentCount: int, permissionNames: list<string>}>
     * }
     */
    public function managementOverview(): array
    {
        try {
            $roleSummaries = $this->roleSummaries();
            $superAdmin = $this->superAdminPosture();

            return [
                'installed' => $this->integrations->resolve()->accessInstalled,
                'available' => true,
                'error' => null,
                'superAdmin' => $superAdmin,
                'roles' => $roleSummaries,
            ];
        } catch (Throwable $exception) {
            return [
                'installed' => $this->integrations->resolve()->accessInstalled,
                'available' => false,
                'error' => $exception->getMessage(),
                'superAdmin' => [
                    'enabled' => false,
                    'roleName' => null,
                    'minimumOperators' => 2,
                    'operatorCount' => 0,
                ],
                'roles' => [],
            ];
        }
    }

    public function syncPermissions(): string
    {
        $storeSetup = $this->resolveService(self::PERMISSION_STORE_SETUP);

        if (! method_exists($storeSetup, 'permissionStoreReady') || ! method_exists($storeSetup, 'hasPendingPublishedMigrations') || ! method_exists($storeSetup, 'synchronizePermissions')) {
            throw new RuntimeException('The access permission store runtime cannot synchronize permissions.');
        }

        if (! $storeSetup->permissionStoreReady()) {
            throw new RuntimeException('The access permission store is not ready for synchronization.');
        }

        if ($storeSetup->hasPendingPublishedMigrations()) {
            throw new RuntimeException('Pending published access migrations must be applied before permissions can be synchronized.');
        }

        $result = $storeSetup->synchronizePermissions();

        return sprintf(
            'Synchronized permissions across %d package(s); %d permission(s) were created and %d permission(s) were already present.',
            count($result->packageNames),
            $result->createdCount,
            $result->unchangedCount,
        );
    }

    public function syncSuggestedRoles(): string
    {
        $roleManager = $this->resolveService(self::ROLE_MANAGER);

        if (! method_exists($roleManager, 'syncRolesFromPermissionHints')) {
            throw new RuntimeException('The access role management runtime cannot synchronize hinted roles.');
        }

        $roleNames = $roleManager->syncRolesFromPermissionHints($this->permissions->all()->all());

        if ($roleNames === []) {
            return 'No permission role hints were available to synchronize.';
        }

        return sprintf(
            'Synchronized %d hinted role(s): %s.',
            count($roleNames),
            implode(', ', $roleNames),
        );
    }

    public function assignRole(int|string $userId, string $roleName, ?Authenticatable $actor = null): void
    {
        $user = $this->resolveUser($userId);

        $userRoleManager = $this->resolveService(self::USER_ROLE_MANAGER);

        if (! method_exists($userRoleManager, 'assignRole')) {
            throw new RuntimeException('The access user-role runtime cannot assign roles.');
        }

        $userRoleManager->assignRole($user, $roleName, $actor);
    }

    public function removeRole(int|string $userId, string $roleName, ?Authenticatable $actor = null): void
    {
        $user = $this->resolveUser($userId);

        $userRoleManager = $this->resolveService(self::USER_ROLE_MANAGER);

        if (! method_exists($userRoleManager, 'removeRole')) {
            throw new RuntimeException('The access user-role runtime cannot remove roles.');
        }

        $userRoleManager->removeRole($user, $roleName, $actor);
    }

    /**
     * @return array<string, string>
     */
    public function roleOptions(): array
    {
        return collect($this->roleSummaries())
            ->pluck('name', 'name')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function persistedPermissionNames(): array
    {
        $permissionModel = $this->permissionModel();

        if ($permissionModel === null || ! $this->tableExists($this->permissionTable())) {
            return [];
        }

        return array_values($permissionModel::query()
            ->orderBy('name')
            ->pluck('name')
            ->all());
    }

    /**
     * @return array<string, list<string>>
     */
    private function roleRelationships(): array
    {
        $roleModel = $this->roleModel();

        if ($roleModel === null || ! $this->tableExists($this->rolesTable())) {
            return [];
        }

        return $roleModel::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(static function (Model $role): array {
                $permissionNames = method_exists($role, 'permissions')
                    ? array_values($role->permissions()->pluck('name')->sort()->values()->all())
                    : [];

                return [(string) $role->getAttribute('name') => $permissionNames];
            })
            ->all();
    }

    /**
     * @return list<array{name: string, permissionCount: int, assignmentCount: int, permissionNames: list<string>}>
     */
    private function roleSummaries(): array
    {
        $roleModel = $this->roleModel();

        if ($roleModel === null || ! $this->tableExists($this->rolesTable())) {
            return [];
        }

        $assignmentCounts = $this->roleAssignmentCounts();

        return array_values($roleModel::query()
            ->orderBy('name')
            ->get()
            ->map(function (Model $role) use ($assignmentCounts): array {
                $roleName = (string) $role->getAttribute('name');
                $permissionNames = method_exists($role, 'permissions')
                    ? array_values($role->permissions()->pluck('name')->sort()->values()->all())
                    : [];

                return [
                    'name' => $roleName,
                    'permissionCount' => count($permissionNames),
                    'assignmentCount' => $assignmentCounts[(string) $role->getKey()] ?? 0,
                    'permissionNames' => $permissionNames,
                ];
            })
            ->all());
    }

    /**
     * @return array<string, int>
     */
    private function roleAssignmentCounts(): array
    {
        $table = $this->modelHasRolesTable();

        if (! $this->tableExists($table)) {
            return [];
        }

        return DB::table($table)
            ->select($this->rolePivotKey())
            ->selectRaw('count(*) as aggregate')
            ->groupBy($this->rolePivotKey())
            ->pluck('aggregate', $this->rolePivotKey())
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @return array{enabled: bool, roleName: ?string, minimumOperators: int, operatorCount: int}
     */
    private function superAdminPosture(): array
    {
        try {
            $guard = $this->resolveService(self::SUPER_ADMIN_SAFETY_GUARD);

            if (! method_exists($guard, 'enabled') || ! method_exists($guard, 'configuredRoleName') || ! method_exists($guard, 'minimumOperators') || ! method_exists($guard, 'currentQualifiedOperatorCount')) {
                throw new RuntimeException('The access super-admin safety runtime is incomplete.');
            }

            return [
                'enabled' => (bool) $guard->enabled(),
                'roleName' => $guard->configuredRoleName(),
                'minimumOperators' => (int) $guard->minimumOperators(),
                'operatorCount' => (int) $guard->currentQualifiedOperatorCount(),
            ];
        } catch (Throwable) {
            return [
                'enabled' => false,
                'roleName' => null,
                'minimumOperators' => 2,
                'operatorCount' => 0,
            ];
        }
    }

    private function resolveUser(int|string $userId): Authenticatable
    {
        $userModel = $this->userModel();

        if ($userModel === null) {
            throw new RuntimeException('The configured host user model is unavailable for access management.');
        }

        $user = $userModel::query()->find($userId);

        if (! $user instanceof Authenticatable) {
            throw new RuntimeException(sprintf('The user [%s] could not be found for access management.', $userId));
        }

        return $user;
    }

    private function resolveService(string $class): object
    {
        if (! $this->integrations->resolve()->accessInstalled) {
            throw new RuntimeException('The access package is not installed for ops access management.');
        }

        if (! class_exists($class)) {
            throw new RuntimeException(sprintf('The access runtime class [%s] is unavailable.', $class));
        }

        return app()->make($class);
    }

    /**
     * @param  array<int, string>  $names
     * @return list<string>
     */
    private function normalizedNames(array $names): array
    {
        $names = array_values(array_filter(array_map(
            static fn (string $name): string => trim($name),
            $names,
        ), static fn (string $name): bool => $name !== ''));

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * @return class-string<Model>|null
     */
    private function permissionModel(): ?string
    {
        return $this->modelConfig('permission.models.permission');
    }

    /**
     * @return class-string<Model>|null
     */
    private function roleModel(): ?string
    {
        return $this->modelConfig('permission.models.role');
    }

    /**
     * @return class-string<Model>|null
     */
    private function userModel(): ?string
    {
        $provider = config('auth.defaults.provider');

        if (! is_string($provider) || $provider === '') {
            return null;
        }

        return $this->modelConfig(sprintf('auth.providers.%s.model', $provider));
    }

    /**
     * @return class-string<Model>|null
     */
    private function modelConfig(string $key): ?string
    {
        $model = config($key);

        if (! is_string($model) || $model === '' || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        return $model;
    }

    private function tableExists(string $table): bool
    {
        return $table !== '' && Schema::hasTable($table);
    }

    private function permissionTable(): string
    {
        return $this->stringConfig('permission.table_names.permissions', 'permissions');
    }

    private function rolesTable(): string
    {
        return $this->stringConfig('permission.table_names.roles', 'roles');
    }

    private function modelHasRolesTable(): string
    {
        return $this->stringConfig('permission.table_names.model_has_roles', 'model_has_roles');
    }

    private function rolePivotKey(): string
    {
        return $this->stringConfig('permission.column_names.role_pivot_key', 'role_id');
    }

    private function stringConfig(string $key, string $fallback): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
