<?php

declare(strict_types=1);

return [
    'panel' => [
        'id' => 'ops',
        'path' => 'ops',
    ],

    'auth' => [
        'guard' => null,
        'host_guard' => 'web',
    ],

    'authorization' => [
        'reduced_mode_ability' => 'viewOpsPanel',
    ],

    'integrations' => [
        'access' => [
            'package' => 'yezzmedia/laravel-access',
        ],
        'health' => [
            'provider' => 'Spatie\\Health\\HealthServiceProvider',
        ],
        'audit' => [
            'provider' => 'Spatie\\Activitylog\\ActivitylogServiceProvider',
            'model' => 'Spatie\\Activitylog\\Models\\Activity',
            'logged_event' => 'Spatie\\Activitylog\\Events\\ActivityLogged',
        ],
    ],

    'diagnostics' => [
        'cooldown_seconds' => 30,
        'lock_seconds' => 30,
        'failing_checks_widget_ttl_seconds' => 30,
        'latest_summary_ttl_seconds' => 300,
    ],

    'packages' => [
        'installed_widget_ttl_seconds' => 300,
    ],

    'audit' => [
        'recent_activity_widget_ttl_seconds' => 30,
    ],
];
