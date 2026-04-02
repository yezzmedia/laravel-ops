<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use YezzMedia\Foundation\Registry\FeatureRegistry;
use YezzMedia\Foundation\Registry\PackageRegistry;
use YezzMedia\Ops\Data\OpsPackageSummary;

/**
 * Builds a curated package overview from foundation registries.
 */
final class OpsPackageSummaryResolver
{
    public function __construct(
        private readonly PackageRegistry $packages,
        private readonly FeatureRegistry $features,
        private readonly OpsNavigationResolver $navigation,
        private readonly OpsPackageSummaryCacheManager $cache,
    ) {}

    public function resolve(): OpsPackageSummary
    {
        $cachedSummary = $this->cache->summary();

        if ($cachedSummary instanceof OpsPackageSummary) {
            return $cachedSummary;
        }

        $packages = $this->packages->all();
        $installedCount = $packages->count();
        $enabledCount = $packages->where('enabled', true)->count();
        $disabledCount = $installedCount - $enabledCount;
        $featurePackageCount = $packages
            ->filter(fn ($package): bool => $this->features->forPackage($package->name)->isNotEmpty())
            ->count();
        $entryPointPackageCount = count($this->navigation->resolve()['Packages']);

        $summary = new OpsPackageSummary(
            installedCount: $installedCount,
            enabledCount: $enabledCount,
            disabledCount: $disabledCount,
            featurePackageCount: $featurePackageCount,
            entryPointPackageCount: $entryPointPackageCount,
            status: $this->status($installedCount, $disabledCount),
        );

        $this->cache->store($summary);

        return $summary;
    }

    private function status(int $installedCount, int $disabledCount): string
    {
        if ($installedCount === 0) {
            return 'empty';
        }

        if ($disabledCount > 0) {
            return 'warnings';
        }

        return 'healthy';
    }
}
