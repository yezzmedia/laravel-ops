<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use YezzMedia\Foundation\Support\IntegrationManager;
use YezzMedia\Ops\Data\OpsIntegrationState;

/**
 * Resolves the explicit optional integration posture used by ops runtime surfaces.
 */
final class OpsIntegrationResolver
{
    public function __construct(private readonly IntegrationManager $integrations) {}

    public function resolve(): OpsIntegrationState
    {
        $accessPackage = config('ops.integrations.access.package');
        $accessInstalled = is_string($accessPackage) && $accessPackage !== ''
            ? $this->integrations->installed($accessPackage)
            : false;

        return new OpsIntegrationState(
            accessMode: $accessInstalled ? 'access_integrated' : 'reduced',
            accessInstalled: $accessInstalled,
            healthInstalled: $this->providerIsAvailable('ops.integrations.health.provider'),
            auditInstalled: $this->providerIsAvailable('ops.integrations.audit.provider'),
        );
    }

    private function providerIsAvailable(string $configKey): bool
    {
        $provider = config($configKey);

        return is_string($provider) && $provider !== '' && class_exists($provider);
    }
}
