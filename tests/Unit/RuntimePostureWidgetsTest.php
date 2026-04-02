<?php

declare(strict_types=1);

use Filament\Widgets\StatsOverviewWidget\Stat;
use YezzMedia\Ops\Widgets\ApplicationRuntimeWidget;
use YezzMedia\Ops\Widgets\IntegrationsRuntimeWidget;

it('builds application runtime stats from page runtime data', function (): void {
    $widget = new class extends ApplicationRuntimeWidget
    {
        /**
         * @return array<Stat>
         */
        public function exposedStats(): array
        {
            return $this->getStats();
        }
    };

    $widget->runtime = [
        [
            'title' => 'Application',
            'items' => [
                [
                    'label' => 'Environment',
                    'value' => 'testing',
                    'description' => 'Current Laravel application environment.',
                ],
                [
                    'label' => 'Debug mode',
                    'value' => 'Enabled',
                    'description' => 'Indicates whether debug mode is enabled for the host application.',
                ],
            ],
        ],
    ];

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(2)
        ->and($stats[0]->getLabel())->toBe('Environment')
        ->and($stats[0]->getValue())->toBe('testing')
        ->and($stats[1]->getLabel())->toBe('Debug mode')
        ->and($stats[1]->getValue())->toBe('Enabled');
});

it('shows an unavailable fallback when a runtime section is missing', function (): void {
    $widget = new class extends IntegrationsRuntimeWidget
    {
        /**
         * @return array<Stat>
         */
        public function exposedStats(): array
        {
            return $this->getStats();
        }
    };

    $stats = $widget->exposedStats();

    expect($stats)->toHaveCount(1)
        ->and($stats[0]->getLabel())->toBe('Status')
        ->and($stats[0]->getValue())->toBe('Unavailable')
        ->and($stats[0]->getDescription())->toBe('Runtime posture data is currently unavailable for this section.');
});
