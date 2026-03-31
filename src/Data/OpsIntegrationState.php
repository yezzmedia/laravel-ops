<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Data;

/**
 * Captures the normalized optional integration posture for ops runtime decisions.
 */
final readonly class OpsIntegrationState
{
    public function __construct(
        public string $accessMode,
        public bool $accessInstalled,
        public bool $healthInstalled,
        public bool $auditInstalled,
    ) {}

    public function accessIntegrated(): bool
    {
        return $this->accessMode === 'access_integrated';
    }

    public function reducedMode(): bool
    {
        return $this->accessMode === 'reduced';
    }

    public function showsAccessSurfaces(): bool
    {
        return $this->accessIntegrated();
    }

    public function showsAccessMutations(): bool
    {
        return $this->accessIntegrated();
    }
}
