<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use YezzMedia\Ops\Support\OpsPackageOverviewResolver;

/**
 * Curates package-level operator visibility for the installed platform.
 */
final class PackagesPage extends OpsPage
{
    protected static string $opsSurface = 'packages';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Packages';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'packages';

    protected string $view = 'ops::pages.packages-page';

    /**
     * @var list<array{name: string, vendor: string, description: string, enabled: bool, priority: ?int, featureCount: int, entryPoints: list<string>}>
     */
    public array $packages = [];

    public function mount(): void
    {
        $this->packages = app(OpsPackageOverviewResolver::class)->resolve();
    }
}
