<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use InvalidArgumentException;

/**
 * Resolves the explicit auth guard configuration for the ops panel.
 */
final class OpsGuardResolver
{
    /**
     * @return array{guard: string, mode: 'dedicated'|'host_boundary'}
     */
    public function resolve(): array
    {
        $dedicatedGuard = $this->configuredGuard('ops.auth.guard');

        if ($dedicatedGuard !== null) {
            if (! $this->guardExists($dedicatedGuard)) {
                throw new InvalidArgumentException(sprintf('Configured ops guard [%s] is not defined.', $dedicatedGuard));
            }

            return [
                'guard' => $dedicatedGuard,
                'mode' => 'dedicated',
            ];
        }

        $hostGuard = $this->configuredGuard('ops.auth.host_guard');

        if ($hostGuard !== null && $this->guardExists($hostGuard)) {
            return [
                'guard' => $hostGuard,
                'mode' => 'host_boundary',
            ];
        }

        throw new InvalidArgumentException('Unable to resolve a safe ops guard configuration.');
    }

    private function configuredGuard(string $key): ?string
    {
        $guard = config($key);

        if (! is_string($guard) || $guard === '') {
            return null;
        }

        return $guard;
    }

    private function guardExists(string $guard): bool
    {
        return is_array(config(sprintf('auth.guards.%s', $guard)));
    }
}
