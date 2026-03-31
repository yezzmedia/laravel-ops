<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class TestOpsUser extends Authenticatable implements FilamentUser
{
    /**
     * @var array<int, string>
     */
    private array $abilities = [];

    public bool $panelAccess = true;

    /**
     * @param  array<int, string>  $abilities
     */
    public static function fixture(array $abilities = [], bool $panelAccess = true, int $id = 1): self
    {
        $user = new self;
        $user->forceFill([
            'id' => $id,
            'name' => 'Ops Tester',
            'email' => 'ops@example.com',
        ]);
        $user->abilities = $abilities;
        $user->panelAccess = $panelAccess;

        return $user;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->panelAccess && $panel->getId() === 'ops';
    }

    public function allows(string $ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }
}
