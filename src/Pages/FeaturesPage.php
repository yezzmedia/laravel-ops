<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use YezzMedia\Ops\Support\OpsFeatureOverviewResolver;

/**
 * Surfaces a feature-oriented platform overview for operators.
 */
final class FeaturesPage extends OpsPage
{
    protected static string $opsSurface = 'features';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Features';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'features';

    protected string $view = 'ops::pages.features-page';

    /**
     * @var list<array{name: string, label: string, package: string, description: ?string, packageDescription: ?string, entryPoints: list<string>}>
     */
    public array $features = [];

    public function mount(): void
    {
        $this->features = app(OpsFeatureOverviewResolver::class)->resolve();
    }
}
