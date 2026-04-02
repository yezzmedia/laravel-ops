<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Renders one runtime posture section as Filament stats.
 */
abstract class RuntimePostureSectionWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    /**
     * @var list<array{title: string, items: list<array{label: string, value: string, description: string}>}>
     */
    public array $runtime = [];

    protected function getHeading(): ?string
    {
        return static::sectionTitle();
    }

    protected function getDescription(): ?string
    {
        return static::sectionDescription();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $section = $this->section();

        if ($section === null) {
            return [
                Stat::make('Status', 'Unavailable')
                    ->description('Runtime posture data is currently unavailable for this section.'),
            ];
        }

        return array_map(
            static fn (array $item): Stat => Stat::make($item['label'], $item['value'])
                ->description($item['description']),
            $section['items'],
        );
    }

    /**
     * @return array{title: string, items: list<array{label: string, value: string, description: string}>}|null
     */
    private function section(): ?array
    {
        foreach ($this->runtime as $section) {
            if ($section['title'] === static::sectionTitle()) {
                return $section;
            }
        }

        return null;
    }

    abstract protected static function sectionTitle(): string;

    abstract protected static function sectionDescription(): string;
}
