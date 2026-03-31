<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

/**
 * Builds a curated runtime posture snapshot for diagnostics-facing pages.
 */
final class OpsRuntimePostureResolver
{
    public function __construct(
        private readonly OpsGuardResolver $guards,
        private readonly OpsIntegrationResolver $integrations,
    ) {}

    /**
     * @return list<array{title: string, items: list<array{label: string, value: string, description: string}>}>
     */
    public function resolve(): array
    {
        $integrationState = $this->integrations->resolve();
        $guard = $this->guards->resolve();

        return [
            [
                'title' => 'Application',
                'items' => [
                    [
                        'label' => 'Environment',
                        'value' => $this->stringConfig('app.env', 'production'),
                        'description' => 'Current Laravel application environment.',
                    ],
                    [
                        'label' => 'Debug mode',
                        'value' => config('app.debug', false) ? 'Enabled' : 'Disabled',
                        'description' => 'Indicates whether debug mode is enabled for the host application.',
                    ],
                    [
                        'label' => 'Ops guard',
                        'value' => $guard['guard'],
                        'description' => sprintf('Resolved ops access boundary in [%s] mode.', $guard['mode']),
                    ],
                ],
            ],
            [
                'title' => 'Drivers',
                'items' => [
                    [
                        'label' => 'Database',
                        'value' => $this->stringConfig('database.default', 'unknown'),
                        'description' => 'Default database connection used by the host application.',
                    ],
                    [
                        'label' => 'Cache',
                        'value' => $this->stringConfig('cache.default', 'unknown'),
                        'description' => 'Default cache store for runtime and package caches.',
                    ],
                    [
                        'label' => 'Queue',
                        'value' => $this->stringConfig('queue.default', 'sync'),
                        'description' => 'Default queue driver for deferred operator-facing work.',
                    ],
                    [
                        'label' => 'Session',
                        'value' => $this->stringConfig('session.driver', 'unknown'),
                        'description' => 'Session driver backing panel authentication state.',
                    ],
                    [
                        'label' => 'Mail',
                        'value' => $this->stringConfig('mail.default', 'unknown'),
                        'description' => 'Default mailer configured by the host application.',
                    ],
                ],
            ],
            [
                'title' => 'Integrations',
                'items' => [
                    [
                        'label' => 'Access mode',
                        'value' => $integrationState->accessInstalled ? 'Access integrated' : 'Reduced mode',
                        'description' => 'Shows whether laravel-access is currently providing the ops authorization boundary.',
                    ],
                    [
                        'label' => 'Health backend',
                        'value' => $integrationState->healthInstalled ? 'Installed' : 'Unavailable',
                        'description' => 'Indicates whether a supported health provider can enrich diagnostics visibility.',
                    ],
                    [
                        'label' => 'Audit backend',
                        'value' => $integrationState->auditInstalled ? 'Installed' : 'Unavailable',
                        'description' => 'Indicates whether a supported audit backend can provide recent activity visibility.',
                    ],
                ],
            ],
        ];
    }

    private function stringConfig(string $key, string $fallback): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
