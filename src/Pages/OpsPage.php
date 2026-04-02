<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use Filament\Pages\Page;
use YezzMedia\Ops\Support\OpsAuthorizationResolver;
use YezzMedia\Ops\Support\OpsSurfaceVisibilityResolver;

/**
 * Centralizes visibility and permission checks for ops-owned custom pages.
 */
abstract class OpsPage extends Page
{
    protected static string $opsSurface;

    public static function canAccess(): bool
    {
        return app(OpsAuthorizationResolver::class)->canAccessSurface(static::$opsSurface);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation()
            && app(OpsSurfaceVisibilityResolver::class)->visible(static::$opsSurface);
    }
}
