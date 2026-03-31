<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Support;

use YezzMedia\Foundation\Data\OpsModuleDefinition;
use YezzMedia\Foundation\Registry\OpsModuleRegistry;

/**
 * Builds a centrally controlled navigation projection from ops-module inputs.
 */
final class OpsNavigationResolver
{
    public function __construct(
        private readonly OpsModuleRegistry $opsModules,
        private readonly OpsSurfaceVisibilityResolver $visibility,
    ) {}

    /**
     * @return array{
     *     Dashboard: array<int, array{key: string, package: string, label: string, type: string, permissionHint: ?string}>,
     *     Packages: array<string, array<int, array{key: string, package: string, label: string, type: string, permissionHint: ?string}>>,
     *     Features: array<int, array{key: string, package: string, label: string, type: string, permissionHint: ?string}>,
     *     Diagnostics: array<int, array{key: string, package: string, label: string, type: string, permissionHint: ?string}>,
     *     Access: array<int, array{key: string, package: string, label: string, type: string, permissionHint: ?string}>,
     *     Audit: array<int, array{key: string, package: string, label: string, type: string, permissionHint: ?string}>
     * }
     */
    public function resolve(): array
    {
        $navigation = [
            'Dashboard' => [],
            'Packages' => [],
            'Features' => [],
            'Diagnostics' => [],
            'Access' => [],
            'Audit' => [],
        ];

        foreach ($this->opsModules->all() as $module) {
            if ($module->type !== 'page') {
                continue;
            }

            $item = $this->normalizeModule($module);

            if (str_starts_with($module->key, 'diagnostics.')) {
                $navigation['Diagnostics'][] = $item;

                continue;
            }

            if (str_starts_with($module->key, 'access.')) {
                if (! $this->visibility->visible('permissions')) {
                    continue;
                }

                $navigation['Access'][] = $item;

                continue;
            }

            if (str_starts_with($module->key, 'audit.')) {
                $navigation['Audit'][] = $item;

                continue;
            }

            $navigation['Packages'][$module->package] ??= [];
            $navigation['Packages'][$module->package][] = $item;
        }

        return $navigation;
    }

    /**
     * @return array{key: string, package: string, label: string, type: string, permissionHint: ?string}
     */
    private function normalizeModule(OpsModuleDefinition $module): array
    {
        return [
            'key' => $module->key,
            'package' => $module->package,
            'label' => $module->label,
            'type' => $module->type,
            'permissionHint' => $module->permissionHint,
        ];
    }
}
