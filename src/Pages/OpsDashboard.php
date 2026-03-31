<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;

/**
 * Provides the operator landing page for the shared ops panel.
 */
final class OpsDashboard extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return app(OpsAuthorizationResolver::class)->canAccessSurface('dashboard');
    }

    public function getColumns(): array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}
