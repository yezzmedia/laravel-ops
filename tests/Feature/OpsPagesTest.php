<?php

declare(strict_types=1);

use Filament\Tables\Table;
use Illuminate\Support\Collection;
use YezzMedia\Access\AccessPlatformPackage;
use YezzMedia\Foundation\Contracts\DefinesPermissions;
use YezzMedia\Foundation\Contracts\PlatformPackage;
use YezzMedia\Foundation\Contracts\ProvidesOpsModules;
use YezzMedia\Foundation\Contracts\RegistersFeatures;
use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Data\FeatureDefinition;
use YezzMedia\Foundation\Data\OpsModuleDefinition;
use YezzMedia\Foundation\Data\PackageMetadata;
use YezzMedia\Foundation\Data\PermissionDefinition;
use YezzMedia\Foundation\Doctor\DoctorManager;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\Ops\Data\OpsRecentActivityItem;
use YezzMedia\Ops\Pages\AccessManagementPage;
use YezzMedia\Ops\Pages\AuditEntryDetailsPage;
use YezzMedia\Ops\Pages\AuditTrailPage;
use YezzMedia\Ops\Pages\DoctorCheckDetailsPage;
use YezzMedia\Ops\Pages\FeaturesPage;
use YezzMedia\Ops\Pages\PackageDetailsPage;
use YezzMedia\Ops\Pages\PackagesPage;
use YezzMedia\Ops\Pages\PermissionDetailsPage;
use YezzMedia\Ops\Pages\PermissionsPage;
use YezzMedia\Ops\Pages\RoleDetailsPage;
use YezzMedia\Ops\Pages\SystemHealthPage;
use YezzMedia\Ops\Support\ActivitylogRecentActivityReader;
use YezzMedia\Ops\Support\OpsAuditEntryDetailsResolver;
use YezzMedia\Ops\Support\OpsRecentActivityCacheManager;
use YezzMedia\Ops\Support\OpsRecentActivityResolver;
use YezzMedia\Ops\Tests\Fixtures\TestOpsUser;

/**
 * @return list<OpsRecentActivityItem>
 */
function auditActivityFixtures(int $count = 50): array
{
    return array_map(
        static function (int $index): OpsRecentActivityItem {
            $events = ['created', 'updated', 'deleted', 'restored'];
            $logs = ['access', 'ops', 'activitylog'];
            $subjects = ['Role', 'Permission', 'Package'];
            $actorNumber = (($index - 1) % 8) + 1;

            return new OpsRecentActivityItem(
                description: sprintf('Audit event #%02d', $index),
                event: $events[($index - 1) % count($events)],
                logName: $logs[($index - 1) % count($logs)],
                loggedAt: now()->subMinutes($index)->toIso8601String(),
                id: sprintf('audit-%02d', $index),
                actorLabel: sprintf('User #%d', $actorNumber),
                subjectLabel: sprintf('%s #%d', $subjects[($index - 1) % count($subjects)], $index),
                contextPreview: sprintf('package=ops.%02d, actor=User #%d', $index, $actorNumber),
                contextRows: [
                    [
                        'key' => 'package',
                        'valuePreview' => sprintf('ops.%02d', $index),
                        'valueRaw' => sprintf('ops.%02d', $index),
                    ],
                    [
                        'key' => 'actor',
                        'valuePreview' => sprintf('User #%d', $actorNumber),
                        'valueRaw' => sprintf('User #%d', $actorNumber),
                    ],
                ],
                changesRows: $index % 2 === 0 ? [
                    [
                        'field' => 'status',
                        'oldPreview' => 'draft',
                        'oldRaw' => 'draft',
                        'newPreview' => 'published',
                        'newRaw' => 'published',
                    ],
                ] : [],
            );
        },
        range(1, $count),
    );
}

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
    $heroSummary = $page->featureHeroSummary();

    expect($page->features)->toHaveCount(5)
        ->and(array_column($page->features, 'name'))->toBe([
            'ops.audit',
            'ops.diagnostics',
            'ops.features',
            'ops.packages',
            'ops.runtime',
        ])
        ->and($heroSummary)->toBe([
            'eyebrow' => 'Ops visibility',
            'heading' => 'Platform feature inventory',
            'description' => 'Review the approved platform features, the packages that contribute them, and which capabilities already expose operator entry points.',
            'featureCount' => 5,
            'featurePackageCount' => 1,
            'featuresWithEntryPointsCount' => 0,
        ])
        ->and($table->getHeading())->toBe('Platform features')
        ->and($table->getDescription())->toBe('Registered platform features with package ownership and related operator entry points.')
        ->and(array_keys($table->getColumns()))->toBe(['name', 'label', 'package', 'description', 'entryPointsLabel'])
        ->and(array_keys($table->getFilters()))->toBe(['package', 'has_entry_points'])
        ->and($table->getHeaderActions())->toHaveCount(1)
        ->and($table->getPaginationPageOptions())->toBe([10, 25, 50])
        ->and($table->getEmptyStateHeading())->toBe('No platform features are currently registered.');

    $diagnosticsPage = app(SystemHealthPage::class);
    $diagnosticsPage->mount();

    $diagnosticsTable = $diagnosticsPage->table(Table::make($diagnosticsPage));
    $diagnosticsHeroSummary = $diagnosticsPage->diagnosticsHeroSummary();
    $runtimeSections = SystemHealthPage::runtimeSectionBlueprintsFor($diagnosticsPage->runtime);

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
        ->and($diagnosticsHeroSummary)->toMatchArray([
            'eyebrow' => 'Ops diagnostics',
            'heading' => 'System health posture',
            'description' => 'Review the current doctor status, current integration posture, and whether the shared runtime surfaces are available to operators.',
        ])
        ->and($diagnosticsTable->getHeading())->toBe('Doctor checks')
        ->and($diagnosticsTable->getDescription())->toBe('Curated diagnostics posture from approved health sources.')
        ->and(array_keys($diagnosticsTable->getColumns()))->toBe(['key', 'package', 'status', 'message'])
        ->and($runtimeSections)->toHaveCount(3)
        ->and($runtimeSections[0])->toMatchArray([
            'title' => 'Application',
            'description' => 'Application environment, debug posture, and resolved ops guard state.',
        ]);

    $auditPage = app(AuditTrailPage::class);
    $auditPage->mount();

    $auditTable = $auditPage->table(Table::make($auditPage));
    $auditHeroSummary = $auditPage->auditHeroSummary();

    expect($auditPage->summary)->toHaveKeys([
        'status',
        'backend',
        'activityCount',
        'latestDescription',
        'latestAt',
        'cachedAt',
        'source',
        'items',
    ])
        ->and($auditHeroSummary)->toMatchArray([
            'eyebrow' => 'Ops audit',
            'heading' => 'Audit activity posture',
            'description' => 'Review audit backend availability, recent operator-visible activity volume, and the newest event currently available through the configured backend.',
        ])
        ->and($auditTable->getHeading())->toBe('Recent audit activity')
        ->and($auditTable->getDescription())->toBe('Privileged and operator-visible activity from the configured audit backend.')
        ->and(array_keys($auditTable->getColumns()))->toBe(['description', 'actorLabel', 'subjectLabel', 'event', 'logName', 'contextPreview', 'loggedAt']);
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
    $heroSummary = $page->featureHeroSummary();

    /** @var Collection<int, array{name: string, label: string, package: string, description: string, packageDescription: string, entryPoints: list<string>, entryPointsLabel: string, sortKey: string}> $featureRecords */
    $featureRecords = (fn (): Collection => $this->featureRecords())->call($page);
    $featureRecord = $featureRecords->sole('name', 'content.pages');

    expect($page->features)->toHaveCount(6)
        ->and(collect($page->features)->contains(fn (array $feature): bool => $feature['name'] === 'content.pages'))->toBeTrue()
        ->and($heroSummary)->toBe([
            'eyebrow' => 'Ops visibility',
            'heading' => 'Platform feature inventory',
            'description' => 'Review the approved platform features, the packages that contribute them, and which capabilities already expose operator entry points.',
            'featureCount' => 6,
            'featurePackageCount' => 2,
            'featuresWithEntryPointsCount' => 0,
        ])
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
    $heroSummary = $page->packagesHeroSummary();

    expect($heroSummary)->toBe([
        'eyebrow' => 'Ops inventory',
        'heading' => 'Platform package inventory',
        'description' => 'Review package readiness, ownership, and how much approved operator-facing surface each package currently contributes.',
        'packageCount' => 3,
        'enabledPackageCount' => 3,
        'entryPointPackageCount' => 1,
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
    $heroSummary = $page->packagesHeroSummary();
    /** @var Collection<int, array{name: string, vendor: string, description: string, packageClass: string, enabled: bool, priority: ?int, posture: string, postureLabel: string, postureTone: string, postureSort: int, featureCount: int, permissionCount: int, opsModuleCount: int, entryPoints: list<string>, entryPointsLabel: string}> $packageRecords */
    $packageRecords = (fn (): Collection => $this->packageRecords())->call($page);

    $contentRecord = $packageRecords->sole('name', 'yezzmedia/laravel-content');
    $catalogRecord = $packageRecords->sole('name', 'yezzmedia/laravel-catalog');

    expect(PackageDetailsPage::shouldRegisterNavigation())->toBeFalse()
        ->and($heroSummary)->toBe([
            'eyebrow' => 'Ops inventory',
            'heading' => 'Platform package inventory',
            'description' => 'Review package readiness, ownership, and how much approved operator-facing surface each package currently contributes.',
            'packageCount' => 4,
            'enabledPackageCount' => 3,
            'entryPointPackageCount' => 1,
        ])
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
    $page->cacheInteractsWithHeaderActions();
    $headerActions = array_values($page->getCachedHeaderActions());
    $headerAction = $headerActions[0];

    expect($page->getTitle())->toBe('yezzmedia/laravel-content')
        ->and($page->getHeading())->toBe('Package details')
        ->and($page->getSubheading())->toBe('Content package.')
        ->and($headerActions)->toHaveCount(1)
        ->and($headerAction->getName())->toBe('backToPackages')
        ->and($headerAction->getUrl())->toBe(PackagesPage::getUrl(panel: (string) config('ops.panel.id', 'ops')))
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
    app()->forgetInstance(ActivitylogRecentActivityReader::class);
    app()->forgetInstance(OpsRecentActivityResolver::class);
    app()->forgetInstance(OpsAuditEntryDetailsResolver::class);
    app(OpsRecentActivityCacheManager::class)->invalidate();
    app()->instance(ActivitylogRecentActivityReader::class, new class extends ActivitylogRecentActivityReader
    {
        public function read(?int $limit = null): array
        {
            return auditActivityFixtures();
        }
    });

    $page = app(AuditTrailPage::class);
    $page->mount();

    /** @var Collection<int, array{id: string, description: string, event: string, logName: string, loggedAt: string, actorLabel: string, subjectLabel: string, contextPreview: ?string, contextRows: list<array{key: string, valuePreview: string, valueRaw: string}>, changesRows: list<array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string}>, sortLoggedAt: string}> $activityRecords */
    $activityRecords = (fn (): Collection => $this->activityRecords())->call($page);
    $activityRecord = $activityRecords->first();

    expect($page->summary['status'])->toBe('available')
        ->and($page->summary['items'])->toHaveCount(50)
        ->and($page->summary['items'][0]['id'])->toBe('audit-01')
        ->and($page->summary['items'][0]['description'])->toBe('Audit event #01')
        ->and($page->summary['items'][0]['logName'])->toBe('access')
        ->and($page->summary['source'] ?? 'fresh read')->toBe('fresh read')
        ->and($activityRecords)->toHaveCount(50)
        ->and($page->table(Table::make($page))->getPaginationPageOptions())->toBe([10, 25, 50])
        ->and($activityRecord['id'])->toBe('audit-01')
        ->and($activityRecord['event'])->toBe('created')
        ->and($activityRecord['logName'])->toBe('access')
        ->and($activityRecord['actorLabel'])->toBe('User #1')
        ->and($activityRecord['subjectLabel'])->toBe('Role #1')
        ->and($activityRecord['contextPreview'])->toBe('package=ops.01, actor=User #1');
});

it('loads audit entry details for one recent activity record', function (): void {
    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    config()->set('ops.integrations.audit.provider', stdClass::class);
    app()->forgetInstance(ActivitylogRecentActivityReader::class);
    app()->forgetInstance(OpsRecentActivityResolver::class);
    app()->forgetInstance(OpsAuditEntryDetailsResolver::class);
    app(OpsRecentActivityCacheManager::class)->invalidate();
    app()->instance(ActivitylogRecentActivityReader::class, new class extends ActivitylogRecentActivityReader
    {
        public function read(?int $limit = null): array
        {
            return auditActivityFixtures();
        }
    });

    $page = app(AuditEntryDetailsPage::class);
    $page->entry = 'audit-01';
    $page->mount();
    $page->cacheInteractsWithHeaderActions();
    $headerActions = array_values($page->getCachedHeaderActions());
    $headerAction = $headerActions[0];

    expect($page->getTitle())->toBe('Audit entry details')
        ->and($page->getHeading())->toBe('Audit entry details')
        ->and($page->getSubheading())->toBe('Audit event #01')
        ->and($headerActions)->toHaveCount(1)
        ->and($headerAction->getName())->toBe('backToAudit')
        ->and($headerAction->getUrl())->toBe(AuditTrailPage::getUrl(panel: (string) config('ops.panel.id', 'ops')))
        ->and($page->details['summary'])->toMatchArray([
            'id' => 'audit-01',
            'event' => 'created',
            'logName' => 'access',
            'actorLabel' => 'User #1',
            'subjectLabel' => 'Role #1',
            'contextPreview' => 'package=ops.01, actor=User #1',
            'sourceLabel' => 'fresh read',
            'backend' => 'activitylog',
        ])
        ->and($page->details['contextRows'])->toContain([
            'key' => 'package',
            'valuePreview' => 'ops.01',
            'valueRaw' => 'ops.01',
        ]);
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
                'context' => [
                    'exception' => RuntimeException::class,
                    'message' => 'Could not connect to the primary database host.',
                ],
            ],
        ],
    ];

    /** @var Collection<int, array{key: string, package: string, status: string, message: string, isBlocking: bool, context: array<string, mixed>|null}> $checkRecords */
    $checkRecords = (fn (): Collection => $this->checkRecords())->call($page);
    $checkRecord = $checkRecords->sole();

    expect($checkRecords)->toHaveCount(1)
        ->and($checkRecord['key'])->toBe('diagnostics.database')
        ->and($checkRecord['status'])->toBe('failed')
        ->and($checkRecord['message'])->toBe('Database connectivity failed.')
        ->and($checkRecord['isBlocking'])->toBeTrue()
        ->and($checkRecord['context'])->toBe([
            'exception' => RuntimeException::class,
            'message' => 'Could not connect to the primary database host.',
        ]);
});

it('loads doctor check details for one diagnostics record', function (): void {
    $user = TestOpsUser::fixture(['viewOpsPanel']);

    auth()->guard('web')->login($user);

    $manager = new class(collect([new DoctorResult('permissions_synchronized', 'yezzmedia/laravel-access', 'failed', 'Declared permissions are missing from the persistent permission store.', true, ['missing_permissions' => ['ops.audit.view'], 'extra_permissions' => ['legacy.permission'], 'declared_permissions' => ['ops.audit.view', 'ops.access.manage'], 'persisted_permissions' => ['ops.access.manage', 'legacy.permission']])])) extends DoctorManager
    {
        /**
         * @param  Collection<int, DoctorResult>  $results
         */
        public function __construct(private readonly Collection $results) {}

        public function run(): Collection
        {
            return $this->results;
        }
    };

    app()->instance(DoctorManager::class, $manager);

    $page = app(DoctorCheckDetailsPage::class);
    $page->package = 'yezzmedia/laravel-access';
    $page->check = 'permissions_synchronized';
    $page->mount();

    expect($page->getTitle())->toBe('permissions_synchronized')
        ->and($page->getHeading())->toBe('Doctor check details')
        ->and($page->details['summary'])->toMatchArray([
            'key' => 'permissions_synchronized',
            'package' => 'yezzmedia/laravel-access',
            'status' => 'failed',
            'statusLabel' => 'Failed',
            'statusTone' => 'danger',
            'blockingLabel' => 'Blocking',
        ])
        ->and($page->details['snapshot']['completedAt'])->not->toBe('')
        ->and($page->details['snapshot'])->toMatchArray([
            'accessMode' => 'Reduced',
            'diagnosticsStatus' => 'Completed',
            'healthInstalled' => false,
            'auditInstalled' => true,
        ])
        ->and($page->details['insights'])->toMatchArray([
            'missingPermissions' => [['value' => 'ops.audit.view']],
            'extraPermissions' => [['value' => 'legacy.permission']],
            'declaredPermissionsCount' => 2,
            'persistedPermissionsCount' => 2,
            'roleName' => null,
        ])
        ->and($page->details['rawContextRows'])->toContain([
            'key' => 'missing_permissions',
            'valuePreview' => 'ops.audit.view',
            'valueRaw' => '["ops.audit.view"]',
        ]);
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
                'packageDescription' => 'Shared operations panel package for the Yezz Media Laravel website platform.',
                'label' => 'View audit surfaces',
                'description' => 'View audit-facing operational visibility surfaces.',
                'synced' => true,
                'roleHints' => ['auditor'],
                'roleHintsCount' => 1,
                'assignedRoles' => ['super-admin'],
                'assignedRoleCount' => 1,
            ],
        ],
        'roles' => [
            [
                'name' => 'super-admin',
                'permissionNames' => ['ops.audit.view'],
            ],
        ],
    ];

    $heroSummary = $page->permissionsHeroSummary();
    $table = $page->table(Table::make($page));
    /** @var Collection<int, array{name: string, package: string, label: string, synced: bool, roleHints: list<string>, assignedRoles: list<string>, roleHintsLabel: string, assignedRolesLabel: string}> $permissionRecords */
    $permissionRecords = (fn (): Collection => $this->permissionRecords())->call($page);
    $permissionRecord = $permissionRecords->sole();

    expect($heroSummary)->toBe([
        'eyebrow' => 'Access visibility',
        'heading' => 'Permission inventory',
        'description' => 'Review declared permissions, sync coverage, and whether the permission store is ready for access operations.',
        'permissionCount' => 1,
        'syncedPermissionCount' => 1,
        'roleCount' => 1,
        'storeStatus' => 'Ready',
    ])
        ->and($table->getHeading())->toBe('Declared permissions')
        ->and($permissionRecords)->toHaveCount(1)
        ->and($permissionRecord['roleHintsCount'])->toBe(1)
        ->and($permissionRecord['assignedRoleCount'])->toBe(1)
        ->and($permissionRecord['roleHintsLabel'])->toBe('auditor')
        ->and($permissionRecord['assignedRolesLabel'])->toBe('super-admin');
});

it('loads the permission details page for one declared permission', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $user = TestOpsUser::fixture([
        'ops.panel.access',
        'ops.access.view',
    ]);

    auth()->guard('web')->login($user);

    $page = app(PermissionDetailsPage::class);
    $page->permission = 'ops.audit.view';
    $page->details = [
        'summary' => [
            'name' => 'ops.audit.view',
            'label' => 'View audit surfaces',
            'package' => 'yezzmedia/laravel-ops',
            'packageDescription' => 'Shared operations panel package for the Yezz Media Laravel website platform.',
            'description' => 'View audit-facing operational visibility surfaces.',
            'syncedLabel' => 'Synced',
            'syncedTone' => 'success',
            'roleHintsCount' => 1,
            'assignedRoleCount' => 1,
        ],
        'roleHints' => ['auditor'],
        'roleHintsLabel' => 'auditor',
        'assignedRoles' => ['super-admin'],
        'assignedRolesLabel' => 'super-admin',
    ];

    $page->cacheInteractsWithHeaderActions();
    $headerActions = array_values($page->getCachedHeaderActions());
    $headerAction = $headerActions[0];

    expect($page->getTitle())->toBe('ops.audit.view')
        ->and($page->getHeading())->toBe('Permission details')
        ->and($page->getSubheading())->toBe('View audit surfaces')
        ->and($headerActions)->toHaveCount(1)
        ->and($headerAction->getName())->toBe('backToPermissions')
        ->and($headerAction->getUrl())->toBe(PermissionsPage::getUrl(panel: (string) config('ops.panel.id', 'ops')))
        ->and($page->details['summary'])->toMatchArray([
            'name' => 'ops.audit.view',
            'label' => 'View audit surfaces',
            'package' => 'yezzmedia/laravel-ops',
            'packageDescription' => 'Shared operations panel package for the Yezz Media Laravel website platform.',
            'description' => 'View audit-facing operational visibility surfaces.',
            'syncedLabel' => 'Synced',
            'syncedTone' => 'success',
            'roleHintsCount' => 1,
            'assignedRoleCount' => 1,
        ])
        ->and($page->details['roleHints'])->toBe(['auditor'])
        ->and($page->details['roleHintsLabel'])->toBe('auditor')
        ->and($page->details['assignedRoles'])->toBe(['super-admin'])
        ->and($page->details['assignedRolesLabel'])->toBe('super-admin');
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

    $heroSummary = $page->accessManagementHeroSummary();
    $table = $page->table(Table::make($page));
    /** @var Collection<int, array{name: string, permissionCount: int, assignmentCount: int, permissionNames: list<string>, permissionNamesLabel: string, isSuperAdminRole: bool, hasAssignments: bool, hasPermissions: bool}> $roleRecords */
    $roleRecords = (fn (): Collection => $this->roleRecords())->call($page);
    $roleRecord = $roleRecords->sole();

    expect($heroSummary)->toBe([
        'eyebrow' => 'Access operations',
        'heading' => 'Role management',
        'description' => 'Review persisted roles, operator coverage, and the current super-admin posture before changing assignments.',
        'roleCount' => 1,
        'assignmentCount' => 2,
        'superAdminRole' => 'super-admin',
        'status' => 'Protected',
    ])
        ->and($table->getHeading())->toBe('Persisted roles')
        ->and(array_keys($table->getFilters()))->toBe(['super_admin', 'has_assignments', 'has_permissions'])
        ->and($roleRecords)->toHaveCount(1)
        ->and($roleRecord['isSuperAdminRole'])->toBeTrue()
        ->and($roleRecord['hasAssignments'])->toBeTrue()
        ->and($roleRecord['hasPermissions'])->toBeTrue()
        ->and($roleRecord['permissionNamesLabel'])->toBe('ops.audit.view, ops.access.manage');
});

it('loads the role details page for one persisted role', function (): void {
    app(PlatformPackageRegistrar::class)->register(new AccessPlatformPackage);

    $user = TestOpsUser::fixture([
        'ops.panel.access',
        'ops.access.manage',
    ]);

    auth()->guard('web')->login($user);

    $page = app(RoleDetailsPage::class);
    $page->role = 'super-admin';
    $page->details = [
        'summary' => [
            'name' => 'super-admin',
            'permissionCount' => 2,
            'assignmentCount' => 3,
            'isSuperAdminRole' => true,
            'superAdminStatus' => 'Super-admin',
        ],
        'permissionNames' => ['ops.audit.view', 'ops.access.manage'],
        'permissionNamesLabel' => 'ops.audit.view, ops.access.manage',
    ];

    $page->cacheInteractsWithHeaderActions();
    $headerActions = array_values($page->getCachedHeaderActions());

    expect($page->getTitle())->toBe('super-admin')
        ->and($page->getHeading())->toBe('Role details')
        ->and($page->getSubheading())->toBe('Super-admin role')
        ->and($headerActions)->toHaveCount(1)
        ->and($headerActions[0]->getName())->toBe('backToAccessManagement')
        ->and($page->details['permissionNamesLabel'])->toBe('ops.audit.view, ops.access.manage');
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

    $permissionsHeroSummary = $permissionsPage->permissionsHeroSummary();
    $permissionsTable = $permissionsPage->table(Table::make($permissionsPage));

    expect($permissionsPage->overview)->toHaveKeys(['installed', 'available', 'error', 'store', 'permissions', 'roles'])
        ->and($permissionsHeroSummary)->toHaveKeys(['eyebrow', 'heading', 'description', 'permissionCount', 'syncedPermissionCount', 'roleCount', 'storeStatus'])
        ->and($permissionsTable->getHeading())->toBe('Declared permissions');

    $accessManagementPage = app(AccessManagementPage::class);
    $accessManagementPage->mount();

    $accessManagementHeroSummary = $accessManagementPage->accessManagementHeroSummary();
    $accessManagementTable = $accessManagementPage->table(Table::make($accessManagementPage));

    expect($accessManagementPage->overview)->toHaveKeys(['installed', 'available', 'error', 'superAdmin', 'roles'])
        ->and($accessManagementHeroSummary)->toHaveKeys(['eyebrow', 'heading', 'description', 'roleCount', 'assignmentCount', 'superAdminRole', 'status'])
        ->and($accessManagementTable->getHeading())->toBe('Persisted roles');
});
