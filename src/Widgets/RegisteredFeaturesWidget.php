<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Support\OpsFeatureOverviewResolver;

/**
 * Surfaces compact feature visibility for the features overview page.
 */
class RegisteredFeaturesWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Features';

    protected ?string $description = 'Registered platform features with package ownership and related operator entry points.';

    protected ?string $pollingInterval = null;

    /**
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $featureCount = count(app(OpsFeatureOverviewResolver::class)->resolve());

        return [
            Stat::make('Feature inventory', $featureCount)
                ->description('Registered platform features with package ownership and related operator entry points.')
                ->descriptionIcon($featureCount > 0 ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedClock)
                ->color($featureCount > 0 ? 'success' : 'gray'),
        ];
    }
}
