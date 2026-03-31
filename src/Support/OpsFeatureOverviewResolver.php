<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use YezzMedia\Foundation\Registry\FeatureRegistry;
use YezzMedia\Foundation\Registry\PackageRegistry;

/**
 * Builds curated feature-level rows for the features overview page.
 */
final class OpsFeatureOverviewResolver
{
    public function __construct(
        private readonly FeatureRegistry $features,
        private readonly PackageRegistry $packages,
        private readonly OpsNavigationResolver $navigation,
    ) {}

    /**
     * @return list<array{name: string, label: string, package: string, description: ?string, packageDescription: ?string, entryPoints: list<string>}>
     */
    public function resolve(): array
    {
        $navigation = $this->navigation->resolve();
        $packageEntryPoints = $navigation['Packages'];

        return array_values($this->features->all()
            ->sortBy([
                ['package', 'asc'],
                ['name', 'asc'],
            ])
            ->map(function ($feature) use ($packageEntryPoints): array {
                $package = $this->packages->find($feature->package);
                $entryPoints = array_map(
                    static fn (array $item): string => $item['label'],
                    $packageEntryPoints[$feature->package] ?? [],
                );

                sort($entryPoints);

                return [
                    'name' => $feature->name,
                    'label' => $feature->label,
                    'package' => $feature->package,
                    'description' => $feature->description,
                    'packageDescription' => $package?->description,
                    'entryPoints' => $entryPoints,
                ];
            })
            ->values()
            ->all());
    }
}
