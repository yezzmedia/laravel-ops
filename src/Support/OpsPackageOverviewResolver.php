<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use YezzMedia\Foundation\Registry\FeatureRegistry;
use YezzMedia\Foundation\Registry\OpsModuleRegistry;
use YezzMedia\Foundation\Registry\PackageRegistry;
use YezzMedia\Foundation\Registry\PermissionRegistry;

/**
 * Builds curated package-level rows for the packages overview page.
 */
final class OpsPackageOverviewResolver
{
    public function __construct(
        private readonly PackageRegistry $packages,
        private readonly FeatureRegistry $features,
        private readonly PermissionRegistry $permissions,
        private readonly OpsModuleRegistry $opsModules,
        private readonly OpsNavigationResolver $navigation,
    ) {}

    /**
     * @return list<array{name: string, vendor: string, description: string, packageClass: string, enabled: bool, priority: ?int, posture: string, postureLabel: string, postureTone: string, postureSort: int, featureCount: int, permissionCount: int, opsModuleCount: int, entryPoints: list<string>}>
     */
    public function resolve(): array
    {
        $navigation = $this->navigation->resolve();
        $packageEntryPoints = $navigation['Packages'];

        return array_values($this->packages->all()
            ->sortBy('name')
            ->map(function ($package) use ($packageEntryPoints): array {
                $featureCount = $this->features->forPackage($package->name)->count();
                $permissionCount = $this->permissions->forPackage($package->name)->count();
                $opsModuleCount = $this->opsModules->forPackage($package->name)->count();
                $entryPoints = array_map(
                    static fn (array $item): string => $item['label'],
                    $packageEntryPoints[$package->name] ?? [],
                );
                $posture = $this->posture(
                    enabled: $package->enabled,
                    featureCount: $featureCount,
                    permissionCount: $permissionCount,
                    opsModuleCount: $opsModuleCount,
                    entryPointCount: count($entryPoints),
                );

                sort($entryPoints);

                return [
                    'name' => $package->name,
                    'vendor' => $package->vendor,
                    'description' => $package->description,
                    'packageClass' => $package->packageClass,
                    'enabled' => $package->enabled,
                    'priority' => $package->priority,
                    'posture' => $posture['state'],
                    'postureLabel' => $posture['label'],
                    'postureTone' => $posture['tone'],
                    'postureSort' => $posture['sort'],
                    'featureCount' => $featureCount,
                    'permissionCount' => $permissionCount,
                    'opsModuleCount' => $opsModuleCount,
                    'entryPoints' => $entryPoints,
                ];
            })
            ->values()
            ->all());
    }

    /**
     * @return array{state: string, label: string, tone: string, sort: int}
     */
    private function posture(bool $enabled, int $featureCount, int $permissionCount, int $opsModuleCount, int $entryPointCount): array
    {
        if (! $enabled) {
            return [
                'state' => 'disabled',
                'label' => 'Disabled',
                'tone' => 'danger',
                'sort' => 0,
            ];
        }

        if (($featureCount + $permissionCount + $opsModuleCount + $entryPointCount) === 0) {
            return [
                'state' => 'limited',
                'label' => 'Limited',
                'tone' => 'warning',
                'sort' => 1,
            ];
        }

        return [
            'state' => 'healthy',
            'label' => 'Healthy',
            'tone' => 'success',
            'sort' => 2,
        ];
    }
}
