<?php

declare(strict_types=1);

use Filament\Tables\Table;
use Illuminate\Support\Collection;
use YezzMedia\Access\AccessPlatformPackage;
use YezzMedia\Foundation\Contracts\DefinesPermissions;
use YezzMedia\Foundation\Contracts\PlatformPackage;
use YezzMedia\Foundation\Contracts\ProvidesOpsModules;
use YezzMedia\Foundation\Contracts\RegistersFeatures;
use YezzMedia\Foundation\Data\FeatureDefinition;
use YezzMedia\Foundation\Data\OpsModuleDefinition;
use YezzMedia\Foundation\Data\PackageMetadata;
use YezzMedia\Foundation\Data\PermissionDefinition;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Data\OpsRecentActivityItem;
use YezzMedia\Ops\Pages\AccessManagementPage;
use YezzMedia\Ops\Pages\AuditTrailPage;
use YezzMedia\Ops\Pages\FeaturesPage;
use YezzMedia\Ops\Pages\PackageDetailsPage;
use YezzMedia\Ops\Pages\PackagesPage;
use YezzMedia\Ops\Pages\PermissionsPage;
use YezzMedia\Ops\Pages\SystemHealthPage;
use YezzMedia\Ops\Support\ActivitylogRecentActivityReader;
use YezzMedia\Ops\Tests\Fixtures\TestOpsUser;
use YezzMedia\Ops\Widgets\ApplicationRuntimeWidget;
use YezzMedia\Ops\Widgets\AuditStatusWidget;
use YezzMedia\Ops\Widgets\DiagnosticsPostureWidget;
use YezzMedia\Ops\Widgets\DriversRuntimeWidget;
use YezzMedia\Ops\Widgets\FailingChecksWidget;
use YezzMedia\Ops\Widgets\InstalledPackagesWidget;
use YezzMedia\Ops\Widgets\IntegrationsRuntimeWidget;
use YezzMedia\Ops\Widgets\RecentActivityWidget;

it('loads the read-oriented ops pages in reduced mode', function (): void {
    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    expect(PackagesPage::shouldRegisterNavigation())->toBeTrue()
        ->and(PackagesPage::canAccess())->toBeTrue()
        ->and(FeaturesPage::shouldRegisterNavigation())->toBeTrue()
        ->and(FeaturesPage::canAccess())->toBeTrue();

    $page = app(FeaturesPage::class);
    $page->mount();

    $table = $page->table(Table::make($page));

    expect($page->features)->toHaveCount(5)
        ->and(array_column($page->features, 'name'))->toBe([
            'ops.audit',
            'ops.diagnostics',
            'ops.features',
            'ops.packages',
            'ops.runtime',
        ])
        ->and($table->getHeading())->toBe('Platform features')
        ->and($table->getDescription())->toBe('Registered platform features with package ownership and related operator entry points.')
        ->and(array_keys($table->getColumns()))->toBe(['label', 'package', 'description', 'entryPointsLabel'])
        ->and($table->getEmptyStateHeading())->toBe('No platform features are currently registered.');

    $diagnosticsPage = app(SystemHealthPage::class);
    $diagnosticsPage->mount();

    $diagnosticsTable = $diagnosticsPage->table(Table::make($diagnosticsPage));
    $headerWidgets = (fn (): array => $this->getHeaderWidgets())->call($diagnosticsPage);
    $footerWidgets = (fn (): array => $this->getFooterWidgets())->call($diagnosticsPage);

    expect($diagnosticsPage->summary)->toHaveKeys([
        'status',
        'failingCount',
        'warningCount',
        'passedCount',
        'skippedCount',
        'completedAt',
        'accessMode',
        'healthInstalled',
        'auditInstalled',
        'checks',
    ])
        ->and($diagnosticsTable->getHeading())->toBe('Doctor checks')
        ->and($diagnosticsTable->getDescription())->toBe('Curated diagnostics posture from approved health sources.')
        ->and(array_keys($diagnosticsTable->getColumns()))->toBe(['key', 'package', 'status', 'message'])
        ->and($headerWidgets)->toBe([
            FailingChecksWidget::class,
            DiagnosticsPostureWidget::class,
        ])
        ->and($footerWidgets)->toBe([
            ApplicationRuntimeWidget::class,
            DriversRuntimeWidget::class,
            IntegrationsRuntimeWidget::class,
        ]);

    $auditPage = app(AuditTrailPage::class);
    $auditPage->mount();

    $auditTable = $auditPage->table(Table::make($auditPage));
    $auditHeaderWidgets = (fn (): array => $this->getHeaderWidgets())->call($auditPage);

    expect($auditPage->summary)->toHaveKeys([
        'status',
        'backend',
        'activityCount',
        'latestDescription',
        'latestAt',
        'items',
    ])
        ->and($auditTable->getHeading())->toBe('Recent audit activity')
        ->and($auditTable->getDescription())->toBe('Privileged and operator-visible activity from the configured audit backend.')
        ->and(array_keys($auditTable->getColumns()))->toBe(['description', 'event', 'logName', 'loggedAt'])
        ->and($auditHeaderWidgets)->toBe([
            RecentActivityWidget::class,
            AuditStatusWidget::class,
        ]);
});

it('loads registered feature records on the features page', function (): void {
    app(PlatformPackageRegistrar::class)->register(new class implements PlatformPackage, RegistersFeatures
    {
        public function metadata(): PackageMetadata
        {
            return new PackageMetadata(
                name: 'yezzmedia/laravel-content',
                vendor: 'yezzmedia',
                description: 'Content package.',
                packageClass: self::class,
            );
        }

        public function featureDefinitions(): array
        {
            return [
                new FeatureDefinition(
                    name: 'content.pages',
                    package: 'yezzmedia/laravel-content',
                    label: 'Content Pages',
                    description: 'Manage editorial pages.',
                ),
            ];
        }
    });

    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    $page = app(FeaturesPage::class);
    $page->mount();

    /** @var Collection<int, array{name: string, label: string, package: string, description: string, packageDescription: string, entryPoints: list<string>, entryPointsLabel: string, sortKey: string}> $featureRecords */
    $featureRecords = (fn (): Collection => $this->featureRecords())->call($page);
    $featureRecord = $featureRecords->sole('name', 'content.pages');

    expect($page->features)->toHaveCount(6)
        ->and(collect($page->features)->contains(fn (array $feature): bool => $feature['name'] === 'content.pages'))->toBeTrue()
        ->and($featureRecord['entryPointsLabel'])->toBe('No package pages')
        ->and($featureRecord['packageDescription'])->toBe('Content package.')
        ->and($featureRecord['label'])->toBe('Content Pages')
        ->and($featureRecord['package'])->toBe('yezzmedia/laravel-content')
        ->and($featureRecord['description'])->toBe('Manage editorial pages.');
});

it('builds a compact package hero summary for the packages page', function (): void {
    app(PlatformPackageRegistrar::class)->register(new class implements PlatformPackage, ProvidesOpsModules, RegistersFeatures
    {
        public function metadata(): PackageMetadata
        {
            return new PackageMetadata(
                name: 'yezzmedia/laravel-content',
                vendor: 'yezzmedia',
                description: 'Content package.',
                packageClass: self::class,
            );
        }

        public function featureDefinitions(): array
        {
            return [
                new FeatureDefinition(
                    name: 'content.pages',
                    package: 'yezzmedia/laravel-content',
                    label: 'Content Pages',
                    description: 'Manage editorial pages.',
                ),
            ];
        }

        public function opsModuleDefinitions(): array
        {
            return [
                new OpsModuleDefinition('content.pages', 'yezzmedia/laravel-content', 'Content Pages', 'page'),
            ];
        }
    });

    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    $page = app(PackagesPage::class);

    $table = $page->table(Table::make($page));
    $headerWidgets = (fn (): array => $this->getHeaderWidgets())->call($page);

    expect($headerWidgets)->toBe([
        InstalledPackagesWidget::class,
    ])
        ->and($table->getHeading())->toBe('Platform packages')
        ->and($table->getDescription())->toBe('Curated package readiness, ownership, and operator-facing entry points.');
});

it('builds package posture and contribution counts for the packages page', function (): void {
    app(PlatformPackageRegistrar::class)->register(new class implements DefinesPermissions, PlatformPackage, ProvidesOpsModules, RegistersFeatures
    {
        public function metadata(): PackageMetadata
        {
            return new PackageMetadata(
                name: 'yezzmedia/laravel-content',
                vendor: 'yezzmedia',
                description: 'Content package.',
                packageClass: self::class,
            );
        }

        public function featureDefinitions(): array
        {
            return [
                new FeatureDefinition('content.pages', 'yezzmedia/laravel-content', 'Content Pages'),
            ];
        }

        public function permissionDefinitions(): array
        {
            return [
                new PermissionDefinition('content.pages.view', 'yezzmedia/laravel-content', 'View content pages'),
            ];
        }

        public function opsModuleDefinitions(): array
        {
            return [
                new OpsModuleDefinition('content.settings', 'yezzmedia/laravel-content', 'Content settings', 'page', 'content.pages.view'),
            ];
        }
    });

    app(PlatformPackageRegistrar::class)->register(new class implements PlatformPackage
    {
        public function metadata(): PackageMetadata
        {
            return new PackageMetadata(
                name: 'yezzmedia/laravel-catalog',
                vendor: 'yezzmedia',
                description: 'Catalog package.',
                packageClass: self::class,
                enabled: false,
            );
        }
    });

    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    $page = app(PackagesPage::class);

    $table = $page->table(Table::make($page));
    /** @var Collection<int, array{name: string, vendor: string, description: string, packageClass: string, enabled: bool, priority: ?int, posture: string, postureLabel: string, postureTone: string, postureSort: int, featureCount: int, permissionCount: int, opsModuleCount: int, entryPoints: list<string>, entryPointsLabel: string}> $packageRecords */
    $packageRecords = (fn (): Collection => $this->packageRecords())->call($page);

    $contentRecord = $packageRecords->sole('name', 'yezzmedia/laravel-content');
    $catalogRecord = $packageRecords->sole('name', 'yezzmedia/laravel-catalog');

    expect(PackageDetailsPage::shouldRegisterNavigation())->toBeFalse()
        ->and($table->getHeading())->toBe('Platform packages')
        ->and(array_keys($table->getColumns()))->toBe([
            'name',
            'vendor',
            'postureSort',
            'enabled',
            'featureCount',
            'permissionCount',
            'opsModuleCount',
            'priority',
            'entryPointsLabel',
        ])
        ->and($contentRecord)->toMatchArray([
            'posture' => 'healthy',
            'postureLabel' => 'Healthy',
            'featureCount' => 1,
            'permissionCount' => 1,
            'opsModuleCount' => 1,
            'entryPointsLabel' => 'Content settings',
        ])
        ->and($catalogRecord)->toMatchArray([
            'posture' => 'disabled',
            'postureLabel' => 'Disabled',
            'featureCount' => 0,
            'permissionCount' => 0,
            'opsModuleCount' => 0,
            'entryPointsLabel' => 'No package pages',
        ]);
});

it('filters package records by package state and contributions', function (): void {
    /**
     * @param  list<FeatureDefinition>  $features
     * @param  list<PermissionDefinition>  $permissions
     * @param  list<OpsModuleDefinition>  $opsModules
     */
    $registerPackage = static function (
        string $name,
        string $description,
        bool $enabled = true,
        array $features = [],
        array $permissions = [],
        array $opsModules = [],
    ): void {
        /** @var list<FeatureDefinition> $features */
        /** @var list<PermissionDefinition> $permissions */
        /** @var list<OpsModuleDefinition> $opsModules */
        app(PlatformPackageRegistrar::class)->register(new class($name, $description, $enabled, $features, $permissions, $opsModules) implements DefinesPermissions, PlatformPackage, ProvidesOpsModules, RegistersFeatures
        {
            /**
             * @param  list<FeatureDefinition>  $features
             * @param  list<PermissionDefinition>  $permissions
             * @param  list<OpsModuleDefinition>  $opsModules
             */
            public function __construct(
                private readonly string $name,
                private readonly string $description,
                private readonly bool $enabled,
                private readonly array $features,
                private readonly array $permissions,
                private readonly array $opsModules,
            ) {}

            public function metadata(): PackageMetadata
            {
                return new PackageMetadata(
                    name: $this->name,
                    vendor: 'yezzmedia',
                    description: $this->description,
                    packageClass: self::class,
                    enabled: $this->enabled,
                );
            }

            public function featureDefinitions(): array
            {
                return $this->features;
            }

            public function permissionDefinitions(): array
            {
                return $this->permissions;
            }

            public function opsModuleDefinitions(): array
            {
                return $this->opsModules;
            }
        });
    };

    $registerPackage(
        'yezzmedia/laravel-content',
        'Content package.',
        features: [
            new FeatureDefinition('content.pages', 'yezzmedia/laravel-content', 'Content Pages'),
        ],
        permissions: [
            new PermissionDefinition('content.pages.view', 'yezzmedia/laravel-content', 'View content pages'),
        ],
        opsModules: [
            new OpsModuleDefinition('content.settings', 'yezzmedia/laravel-content', 'Content settings', 'page', 'content.pages.view'),
        ],
    );

    $registerPackage(
        'yezzmedia/laravel-blog',
        'Blog package.',
        features: [
            new FeatureDefinition('blog.posts', 'yezzmedia/laravel-blog', 'Blog Posts'),
        ],
    );

    $registerPackage('yezzmedia/laravel-docs', 'Docs package.');
    $registerPackage('yezzmedia/laravel-catalog', 'Catalog package.', enabled: false);

    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    $page = app(PackagesPage::class);
    $table = $page->table(Table::make($page));

    /** @var Collection<int, array{name: string, vendor: string, description: string, packageClass: string, enabled: bool, priority: ?int, posture: string, postureLabel: string, postureTone: string, postureSort: int, featureCount: int, permissionCount: int, opsModuleCount: int, entryPoints: list<string>, entryPointsLabel: string}> $packageRecords */
    $packageRecords = (fn (): Collection => $this->packageRecords())->call($page);

    $enabledPackages = $packageRecords->where('enabled', true)->values();
    $disabledPackages = $packageRecords->where('enabled', false)->values();
    $healthyPackages = $packageRecords->where('posture', 'healthy')->values();
    $limitedPackages = $packageRecords->where('posture', 'limited')->values();
    $disabledPosturePackages = $packageRecords->where('posture', 'disabled')->values();
    $featurePackages = $packageRecords->filter(fn (array $record): bool => $record['featureCount'] > 0)->values();
    $packagesWithoutFeatures = $packageRecords->reject(fn (array $record): bool => $record['featureCount'] > 0)->values();
    $permissionPackages = $packageRecords->filter(fn (array $record): bool => $record['permissionCount'] > 0)->values();
    $packagesWithoutPermissions = $packageRecords->reject(fn (array $record): bool => $record['permissionCount'] > 0)->values();
    $opsModulePackages = $packageRecords->filter(fn (array $record): bool => $record['opsModuleCount'] > 0)->values();
    $packagesWithoutOpsModules = $packageRecords->reject(fn (array $record): bool => $record['opsModuleCount'] > 0)->values();
    $entryPointPackages = $packageRecords->filter(fn (array $record): bool => $record['entryPoints'] !== [])->values();
    $packagesWithoutEntryPoints = $packageRecords->reject(fn (array $record): bool => $record['entryPoints'] !== [])->values();

    $applyFilters = new ReflectionMethod($page, 'applyFilters');
    $applyFilters->setAccessible(true);

    expect(array_keys($table->getFilters()))->toBe([
        'enabled',
        'posture',
        'has_features',
        'has_permissions',
        'has_ops_modules',
        'has_entry_points',
    ])
        ->and($applyFilters->invoke($page, $packageRecords, ['enabled' => ['value' => 'enabled']])->values()->all())->toBe($enabledPackages->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['enabled' => ['value' => 'disabled']])->values()->all())->toBe($disabledPackages->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['posture' => ['value' => 'healthy']])->values()->all())->toBe($healthyPackages->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['posture' => ['value' => 'limited']])->values()->all())->toBe($limitedPackages->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['posture' => ['value' => 'disabled']])->values()->all())->toBe($disabledPosturePackages->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['has_features' => ['isActive' => true]])->values()->all())->toBe($featurePackages->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['has_features' => ['isActive' => true]])->values()->all())->not->toBe($packagesWithoutFeatures->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['has_permissions' => ['isActive' => true]])->values()->all())->toBe($permissionPackages->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['has_permissions' => ['isActive' => true]])->values()->all())->not->toBe($packagesWithoutPermissions->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['has_ops_modules' => ['isActive' => true]])->values()->all())->toBe($opsModulePackages->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['has_ops_modules' => ['isActive' => true]])->values()->all())->not->toBe($packagesWithoutOpsModules->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['has_entry_points' => ['isActive' => true]])->values()->all())->toBe($entryPointPackages->all())
        ->and($applyFilters->invoke($page, $packageRecords, ['has_entry_points' => ['isActive' => true]])->values()->all())->not->toBe($packagesWithoutEntryPoints->all());
});

it('loads package details for one registered package', function (): void {
    app(PlatformPackageRegistrar::class)->register(new class implements DefinesPermissions, PlatformPackage, ProvidesOpsModules, RegistersFeatures
    {
        public function metadata(): PackageMetadata
        {
            return new PackageMetadata(
                name: 'yezzmedia/laravel-content',
                vendor: 'yezzmedia',
                description: 'Content package.',
                packageClass: self::class,
            );
        }

        public function featureDefinitions(): array
        {
            return [
                new FeatureDefinition(
                    name: 'content.pages',
                    package: 'yezzmedia/laravel-content',
                    label: 'Content Pages',
                    description: 'Manage editorial pages.',
                ),
            ];
        }

        public function permissionDefinitions(): array
        {
            return [
                new PermissionDefinition(
                    name: 'content.pages.view',
                    package: 'yezzmedia/laravel-content',
                    label: 'View content pages',
                    description: 'Inspect editorial pages.',
                ),
            ];
        }

        public function opsModuleDefinitions(): array
        {
            return [
                new OpsModuleDefinition('content.settings', 'yezzmedia/laravel-content', 'Content settings', 'page', 'content.pages.view'),
            ];
        }
    });

    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    $page = app(PackageDetailsPage::class);
    $page->package = 'yezzmedia/laravel-content';
    $page->mount();

    expect($page->getTitle())->toBe('yezzmedia/laravel-content')
        ->and($page->getHeading())->toBe('Package details')
        ->and($page->getSubheading())->toBe('Content package.')
        ->and($page->details['posture'])->toMatchArray([
            'state' => 'healthy',
            'label' => 'Healthy',
            'tone' => 'success',
        ])
        ->and($page->details['counts'])->toBe([
            'features' => 1,
            'permissions' => 1,
            'opsModules' => 1,
            'entryPoints' => 1,
        ])
        ->and($page->details['metadata']['packageClass'])->not->toBe('')
        ->and($page->details['features'][0])->toMatchArray([
            'name' => 'content.pages',
            'label' => 'Content Pages',
            'description' => 'Manage editorial pages.',
        ])
        ->and($page->details['permissions'][0])->toMatchArray([
            'name' => 'content.pages.view',
            'label' => 'View content pages',
            'description' => 'Inspect editorial pages.',
        ])
        ->and($page->details['opsModules'][0])->toMatchArray([
            'key' => 'content.settings',
            'label' => 'Content settings',
            'type' => 'page',
            'permissionHint' => 'content.pages.view',
        ])
        ->and($page->details['entryPoints'][0])->toMatchArray([
            'label' => 'Content settings',
            'permissionHint' => 'content.pages.view',
            'url' => null,
        ]);
});

it('loads the audit page when recent activity items are available', function (): void {
    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    config()->set('ops.integrations.audit.provider', stdClass::class);
    app()->instance(ActivitylogRecentActivityReader::class, new class extends ActivitylogRecentActivityReader
    {
        public function read(int $limit = 5): array
        {
            return [
                new OpsRecentActivityItem('Permissions synchronized.', 'updated', 'access', now()->toIso8601String()),
            ];
        }
    });

    $page = app(AuditTrailPage::class);
    $page->mount();

    /** @var Collection<int, array{description: string, event: string, logName: string, loggedAt: string, sortLoggedAt: string}> $activityRecords */
    $activityRecords = (fn (): Collection => $this->activityRecords())->call($page);
    $activityRecord = $activityRecords->sole();

    expect($page->summary['status'])->toBe('available')
        ->and($page->summary['items'][0]['description'])->toBe('Permissions synchronized.')
        ->and($page->summary['items'][0]['logName'])->toBe('access')
        ->and($activityRecords)->toHaveCount(1)
        ->and($activityRecord['event'])->toBe('updated')
        ->and($activityRecord['logName'])->toBe('access');
});

it('normalizes diagnostics check records for the doctor checks table', function (): void {
    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    $page = app(SystemHealthPage::class);
    $page->summary = [
        'status' => 'completed',
        'failingCount' => 1,
        'warningCount' => 0,
        'passedCount' => 2,
        'skippedCount' => 0,
        'completedAt' => now()->toIso8601String(),
        'accessMode' => 'reduced',
        'healthInstalled' => false,
        'auditInstalled' => false,
        'checks' => [
            [
                'key' => 'diagnostics.database',
                'package' => 'yezzmedia/laravel-ops',
                'status' => 'failed',
                'message' => 'Database connectivity failed.',
                'isBlocking' => true,
            ],
        ],
    ];

    /** @var Collection<int, array{key: string, package: string, status: string, message: string, isBlocking: bool}> $checkRecords */
    $checkRecords = (fn (): Collection => $this->checkRecords())->call($page);
    $checkRecord = $checkRecords->sole();

    expect($checkRecords)->toHaveCount(1)
        ->and($checkRecord['key'])->toBe('diagnostics.database')
        ->and($checkRecord['status'])->toBe('failed')
        ->and($checkRecord['message'])->toBe('Database connectivity failed.')
        ->and($checkRecord['isBlocking'])->toBeTrue();
});

it('normalizes declared permission rows for the permissions table', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $user = TestOpsUser::fixture([
        'ops.panel.access',
        'ops.access.view',
    ]);

    auth()->guard('web')->login($user);

    $page = app(PermissionsPage::class);
    $page->overview = [
        'installed' => true,
        'available' => true,
        'error' => null,
        'store' => [
            'configPublished' => true,
            'migrationsPublished' => true,
            'pendingMigrations' => false,
            'ready' => true,
        ],
        'permissions' => [
            [
                'name' => 'ops.audit.view',
                'package' => 'yezzmedia/laravel-ops',
                'label' => 'View audit surfaces',
                'synced' => true,
                'roleHints' => ['auditor'],
                'assignedRoles' => ['super-admin'],
            ],
        ],
        'roles' => [
            [
                'name' => 'super-admin',
                'permissionNames' => ['ops.audit.view'],
            ],
        ],
    ];

    $table = $page->table(Table::make($page));
    /** @var Collection<int, array{name: string, package: string, label: string, synced: bool, roleHints: list<string>, assignedRoles: list<string>, roleHintsLabel: string, assignedRolesLabel: string}> $permissionRecords */
    $permissionRecords = (fn (): Collection => $this->permissionRecords())->call($page);
    $permissionRecord = $permissionRecords->sole();

    expect($table->getHeading())->toBe('Declared permissions')
        ->and(array_keys($table->getColumns()))->toBe(['name', 'package', 'synced', 'roleHintsLabel', 'assignedRolesLabel'])
        ->and($permissionRecords)->toHaveCount(1)
        ->and($permissionRecord['roleHintsLabel'])->toBe('auditor')
        ->and($permissionRecord['assignedRolesLabel'])->toBe('super-admin');
});

it('normalizes persisted role rows for access management', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $user = TestOpsUser::fixture([
        'ops.panel.access',
        'ops.access.manage',
    ]);

    auth()->guard('web')->login($user);

    $page = app(AccessManagementPage::class);
    $page->overview = [
        'installed' => true,
        'available' => true,
        'error' => null,
        'superAdmin' => [
            'enabled' => true,
            'roleName' => 'super-admin',
            'minimumOperators' => 2,
            'operatorCount' => 3,
        ],
        'roles' => [
            [
                'name' => 'super-admin',
                'permissionCount' => 4,
                'assignmentCount' => 2,
                'permissionNames' => ['ops.audit.view', 'ops.access.manage'],
            ],
        ],
    ];

    $table = $page->table(Table::make($page));
    /** @var Collection<int, array{name: string, permissionCount: int, assignmentCount: int, permissionNames: list<string>, permissionNamesLabel: string}> $roleRecords */
    $roleRecords = (fn (): Collection => $this->roleRecords())->call($page);
    $roleRecord = $roleRecords->sole();

    expect($table->getHeading())->toBe('Persisted roles')
        ->and(array_keys($table->getColumns()))->toBe(['name', 'permissionCount', 'assignmentCount', 'permissionNamesLabel'])
        ->and($roleRecords)->toHaveCount(1)
        ->and($roleRecord['permissionNamesLabel'])->toBe('ops.audit.view, ops.access.manage');
});

it('forbids access pages in reduced mode', function (): void {
    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    expect(PermissionsPage::shouldRegisterNavigation())->toBeFalse()
        ->and(PermissionsPage::canAccess())->toBeFalse()
        ->and(AccessManagementPage::shouldRegisterNavigation())->toBeFalse()
        ->and(AccessManagementPage::canAccess())->toBeFalse();
});

it('loads the access pages when access integration is active and the operator has explicit permissions', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $user = TestOpsUser::fixture([
        'ops.panel.access',
        'ops.access.view',
        'ops.access.manage',
    ]);

    auth()->guard('web')->login($user);

    expect(PermissionsPage::shouldRegisterNavigation())->toBeTrue()
        ->and(PermissionsPage::canAccess())->toBeTrue()
        ->and(AccessManagementPage::shouldRegisterNavigation())->toBeTrue()
        ->and(AccessManagementPage::canAccess())->toBeTrue();

    $permissionsPage = app(PermissionsPage::class);
    $permissionsPage->mount();

    $permissionsTable = $permissionsPage->table(Table::make($permissionsPage));

    expect($permissionsPage->overview)->toHaveKeys(['installed', 'available', 'error', 'store', 'permissions', 'roles'])
        ->and($permissionsTable->getHeading())->toBe('Declared permissions');

    $accessManagementPage = app(AccessManagementPage::class);
    $accessManagementPage->mount();

    $accessManagementTable = $accessManagementPage->table(Table::make($accessManagementPage));

    expect($accessManagementPage->overview)->toHaveKeys(['installed', 'available', 'error', 'superAdmin', 'roles'])
        ->and($accessManagementTable->getHeading())->toBe('Persisted roles');
});
