<?php

declare(strict_types=1);

use YezzMedia\Foundation\Contracts\PlatformPackage;
use YezzMedia\Foundation\Contracts\ProvidesOpsModules;
use YezzMedia\Foundation\Contracts\RegistersFeatures;
use YezzMedia\Foundation\Data\FeatureDefinition;
use YezzMedia\Foundation\Data\OpsModuleDefinition;
use YezzMedia\Foundation\Data\PackageMetadata;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Data\OpsPackageSummary;
use YezzMedia\Ops\Support\OpsPackageSummaryCacheManager;
use YezzMedia\Ops\Support\OpsPackageSummaryResolver;

it('builds and caches a curated package summary from foundation registries', function (): void {
    app(PlatformPackageRegistrar::class)->register(new class implements PlatformPackage, ProvidesOpsModules, RegistersFeatures
    {
        public function metadata(): PackageMetadata
        {
            return new PackageMetadata(
                name: 'yezzmedia/laravel-content',
                vendor: 'yezzmedia',
                description: 'Content package.',
                packageClass: self::class,
            );
        }

        public function featureDefinitions(): array
        {
            return [
                new FeatureDefinition('content.pages', 'yezzmedia/laravel-content', 'Content Pages'),
            ];
        }

        public function opsModuleDefinitions(): array
        {
            return [
                new OpsModuleDefinition('content.pages', 'yezzmedia/laravel-content', 'Content Pages', 'page'),
            ];
        }
    });

    app(PlatformPackageRegistrar::class)->register(new class implements PlatformPackage
    {
        public function metadata(): PackageMetadata
        {
            return new PackageMetadata(
                name: 'yezzmedia/laravel-catalog',
                vendor: 'yezzmedia',
                description: 'Catalog package.',
                packageClass: self::class,
                enabled: false,
            );
        }
    });

    $resolver = app(OpsPackageSummaryResolver::class);
    $summary = $resolver->resolve();

    expect($summary)->toBeInstanceOf(OpsPackageSummary::class)
        ->and($summary->installedCount)->toBe(4)
        ->and($summary->enabledCount)->toBe(3)
        ->and($summary->disabledCount)->toBe(1)
        ->and($summary->featurePackageCount)->toBe(2)
        ->and($summary->entryPointPackageCount)->toBe(1)
        ->and($summary->status)->toBe('warnings')
        ->and(app(OpsPackageSummaryCacheManager::class)->summary())->toBeInstanceOf(OpsPackageSummary::class);
});
