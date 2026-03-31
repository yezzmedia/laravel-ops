<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

/**
 * Surfaces package integration posture relevant to diagnostics visibility.
 */
class IntegrationsRuntimeWidget extends RuntimePostureSectionWidget
{
    protected static function sectionTitle(): string
    {
        return 'Integrations';
    }

    protected static function sectionDescription(): string
    {
        return 'Installed package integrations that enrich diagnostics and operator visibility.';
    }
}
