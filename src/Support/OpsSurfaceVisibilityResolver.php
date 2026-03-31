<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

/**
 * Resolves which operator surfaces are visible for the current integration mode.
 */
final class OpsSurfaceVisibilityResolver
{
    public function __construct(private readonly OpsIntegrationResolver $integrations) {}

    public function visible(string $surface): bool
    {
        return match ($surface) {
            'permissions', 'access_management' => $this->integrations->resolve()->accessIntegrated(),
            default => true,
        };
    }
}
