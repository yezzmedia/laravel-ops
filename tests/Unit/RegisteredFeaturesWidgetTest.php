<?php

declare(strict_types=1);

use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Foundation\Contracts\PlatformPackage;
use YezzMedia\Foundation\Contracts\RegistersFeatures;
use YezzMedia\Foundation\Data\FeatureDefinition;
use YezzMedia\Foundation\Data\PackageMetadata;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Widgets\RegisteredFeaturesWidget;

it('builds operator-facing feature stats from the feature registry', function (): void {
    app(PlatformPackageRegistrar::class)->register(new class implements PlatformPackage, RegistersFeatures
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
                new FeatureDefinition('content.sections', 'yezzmedia/laravel-content', 'Content Sections'),
            ];
        }
    });

    $widget = new class extends RegisteredFeaturesWidget
    {
        /**
         * @return array<Stat>
         */
        public function exposedStats(): array
        {
            return $this->getStats();
        }
    };

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(1)
        ->and($stats[0]->getLabel())->toBe('Feature inventory')
        ->and($stats[0]->getValue())->toBe(7)
        ->and($stats[0]->getDescription())->toBe('Registered platform features with package ownership and related operator entry points.');
});

it('shows the ops-owned feature inventory by default', function (): void {
    $widget = new class extends RegisteredFeaturesWidget
    {
        /**
         * @return array<Stat>
         */
        public function exposedStats(): array
        {
            return $this->getStats();
        }
    };

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(1)
        ->and($stats[0]->getLabel())->toBe('Feature inventory')
        ->and($stats[0]->getValue())->toBe(5)
        ->and($stats[0]->getDescription())->toBe('Registered platform features with package ownership and related operator entry points.');
});
