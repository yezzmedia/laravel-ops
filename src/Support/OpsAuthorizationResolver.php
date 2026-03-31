<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Centralizes panel and surface authorization decisions for ops runtime flows.
 */
final class OpsAuthorizationResolver
{
    /**
     * @var array<string, string>
     */
    private const ACCESS_INTEGRATED_SURFACE_ABILITIES = [
        'dashboard' => 'ops.dashboard.view',
        'packages' => 'ops.packages.view',
        'features' => 'ops.features.view',
        'diagnostics' => 'ops.diagnostics.view',
        'runtime' => 'ops.runtime.view',
        'audit' => 'ops.audit.view',
        'permissions' => 'ops.access.view',
        'access_management' => 'ops.access.manage',
    ];

    /**
     * @var array<int, string>
     */
    private const REDUCED_SURFACES = [
        'dashboard',
        'packages',
        'features',
        'diagnostics',
        'runtime',
        'audit',
    ];

    public function __construct(
        private readonly OpsGuardResolver $guards,
        private readonly OpsIntegrationResolver $integrations,
        private readonly OpsSurfaceVisibilityResolver $visibility,
    ) {}

    public function canAccessPanel(Panel $panel, ?Authenticatable $user = null): bool
    {
        $user ??= $this->currentUser();

        if (! $user instanceof FilamentUser || ! $user->canAccessPanel($panel)) {
            return false;
        }

        $ability = $this->integrations->resolve()->accessIntegrated()
            ? 'ops.panel.access'
            : $this->reducedModeAbility();

        return Gate::forUser($user)->allows($ability);
    }

    public function canAccessSurface(string $surface, ?Authenticatable $user = null): bool
    {
        if (! $this->visibility->visible($surface)) {
            return false;
        }

        $user ??= $this->currentUser();

        if (! $user instanceof Authenticatable) {
            return false;
        }

        $ability = $this->surfaceAbility($surface);

        return $ability !== null && Gate::forUser($user)->allows($ability);
    }

    public function authorizeSurface(string $surface, ?Authenticatable $user = null): void
    {
        if (! $this->canAccessSurface($surface, $user)) {
            throw new AuthorizationException(sprintf('This operator cannot access the [%s] surface.', $surface));
        }
    }

    private function currentUser(): ?Authenticatable
    {
        $guard = $this->guards->resolve()['guard'];
        $user = Auth::guard($guard)->user();

        return $user instanceof Authenticatable ? $user : null;
    }

    private function reducedModeAbility(): string
    {
        $ability = config('ops.authorization.reduced_mode_ability', 'viewOpsPanel');

        return is_string($ability) && $ability !== '' ? $ability : 'viewOpsPanel';
    }

    private function surfaceAbility(string $surface): ?string
    {
        if ($this->integrations->resolve()->accessIntegrated()) {
            return self::ACCESS_INTEGRATED_SURFACE_ABILITIES[$surface] ?? null;
        }

        return in_array($surface, self::REDUCED_SURFACES, true)
            ? $this->reducedModeAbility()
            : null;
    }
}
