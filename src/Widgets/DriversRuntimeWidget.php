<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

/**
 * Surfaces host driver posture relevant to diagnostics and ops flows.
 */
class DriversRuntimeWidget extends RuntimePostureSectionWidget
{
    protected static function sectionTitle(): string
    {
        return 'Drivers';
    }

    protected static function sectionDescription(): string
    {
        return 'Default host drivers that influence operator-facing runtime behavior.';
    }
}
