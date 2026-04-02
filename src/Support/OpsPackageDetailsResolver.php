<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use YezzMedia\Foundation\Data\PackageMetadata;
use YezzMedia\Foundation\Registry\FeatureRegistry;
use YezzMedia\Foundation\Registry\OpsModuleRegistry;
use YezzMedia\Foundation\Registry\PackageRegistry;
use YezzMedia\Foundation\Registry\PermissionRegistry;

/**
 * Builds a read-oriented package drilldown from approved foundation registries.
 */
final class OpsPackageDetailsResolver
{
    public function __construct(
        private readonly PackageRegistry $packages,
        private readonly FeatureRegistry $features,
        private readonly PermissionRegistry $permissions,
        private readonly OpsModuleRegistry $opsModules,
        private readonly OpsNavigationResolver $navigation,
        private readonly OpsPackageOverviewResolver $overview,
    ) {}

    /**
     * @return array{
     *     metadata: array{name: string, vendor: string, description: string, packageClass: string, enabled: bool, priority: ?int},
     *     posture: array{state: string, label: string, tone: string},
     *     counts: array{features: int, permissions: int, opsModules: int, entryPoints: int},
     *     features: list<array{name: string, label: string, description: ?string}>,
     *     permissions: list<array{name: string, label: string, description: ?string}>,
     *     opsModules: list<array{key: string, label: string, type: string, permissionHint: ?string}>,
     *     entryPoints: list<array{label: string, permissionHint: ?string, url: ?string}>
     * }
     */
    public function resolve(string $packageName): array
    {
        $metadata = $this->packages->find($packageName);

        if (! $metadata instanceof PackageMetadata) {
            throw new NotFoundHttpException(sprintf('Package [%s] is not registered.', $packageName));
        }

        $overview = collect($this->overview->resolve())->firstWhere('name', $packageName);

        if (! is_array($overview)) {
            throw new NotFoundHttpException(sprintf('Package [%s] is not available in the ops package overview.', $packageName));
        }

        $features = $this->features->forPackage($packageName)
            ->sortBy('name')
            ->map(static fn ($feature): array => [
                'name' => $feature->name,
                'label' => $feature->label,
                'description' => $feature->description,
            ])
            ->values()
            ->all();

        $permissions = $this->permissions->forPackage($packageName)
            ->sortBy('name')
            ->map(static fn ($permission): array => [
                'name' => $permission->name,
                'label' => $permission->label,
                'description' => $permission->description,
            ])
            ->values()
            ->all();

        $opsModules = $this->opsModules->forPackage($packageName)
            ->sortBy('key')
            ->map(static fn ($module): array => [
                'key' => $module->key,
                'label' => $module->label,
                'type' => $module->type,
                'permissionHint' => $module->permissionHint,
            ])
            ->values()
            ->all();

        $entryPoints = collect($this->navigation->resolve()['Packages'][$packageName] ?? [])
            ->sortBy('label')
            ->map(static fn (array $entryPoint): array => [
                'label' => $entryPoint['label'],
                'permissionHint' => $entryPoint['permissionHint'],
                'url' => Arr::get($entryPoint, 'url'),
            ])
            ->values()
            ->all();

        return [
            'metadata' => [
                'name' => $metadata->name,
                'vendor' => $metadata->vendor,
                'description' => $metadata->description,
                'packageClass' => $metadata->packageClass,
                'enabled' => $metadata->enabled,
                'priority' => $metadata->priority,
            ],
            'posture' => [
                'state' => $overview['posture'],
                'label' => $overview['postureLabel'],
                'tone' => $overview['postureTone'],
            ],
            'counts' => [
                'features' => count($features),
                'permissions' => count($permissions),
                'opsModules' => count($opsModules),
                'entryPoints' => count($entryPoints),
            ],
            'features' => $features,
            'permissions' => $permissions,
            'opsModules' => $opsModules,
            'entryPoints' => $entryPoints,
        ];
    }
}
