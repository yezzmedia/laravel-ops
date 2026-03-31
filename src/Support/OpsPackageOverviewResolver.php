<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use YezzMedia\Foundation\Registry\FeatureRegistry;
use YezzMedia\Foundation\Registry\PackageRegistry;

/**
 * Builds curated package-level rows for the packages overview page.
 */
final class OpsPackageOverviewResolver
{
    public function __construct(
        private readonly PackageRegistry $packages,
        private readonly FeatureRegistry $features,
        private readonly OpsNavigationResolver $navigation,
    ) {}

    /**
     * @return list<array{name: string, vendor: string, description: string, enabled: bool, priority: ?int, featureCount: int, entryPoints: list<string>}>
     */
    public function resolve(): array
    {
        $navigation = $this->navigation->resolve();
        $packageEntryPoints = $navigation['Packages'];

        return array_values($this->packages->all()
            ->sortBy('name')
            ->map(function ($package) use ($packageEntryPoints): array {
                $entryPoints = array_map(
                    static fn (array $item): string => $item['label'],
                    $packageEntryPoints[$package->name] ?? [],
                );

                sort($entryPoints);

                return [
                    'name' => $package->name,
                    'vendor' => $package->vendor,
                    'description' => $package->description,
                    'enabled' => $package->enabled,
                    'priority' => $package->priority,
                    'featureCount' => $this->features->forPackage($package->name)->count(),
                    'entryPoints' => $entryPoints,
                ];
            })
            ->values()
            ->all());
    }
}
