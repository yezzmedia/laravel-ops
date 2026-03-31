<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

/**
 * Surfaces application-level runtime posture details.
 */
class ApplicationRuntimeWidget extends RuntimePostureSectionWidget
{
    protected static function sectionTitle(): string
    {
        return 'Application';
    }

    protected static function sectionDescription(): string
    {
        return 'Application environment, debug posture, and resolved ops guard state.';
    }
}
