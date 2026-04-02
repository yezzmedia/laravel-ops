<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;
use YezzMedia\Ops\Support\OpsPackageDetailsResolver;

/**
 * Surfaces one package's curated platform contributions for operators.
 */
final class PackageDetailsPage extends OpsPage
{
    protected static string $opsSurface = 'packages';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?string $slug = 'packages/details';

    protected string $view = 'ops::pages.package-details-page';

    #[Url]
    public string $package = '';

    /**
     * @var array{
     *     metadata: array{name: string, vendor: string, description: string, packageClass: string, enabled: bool, priority: ?int},
     *     posture: array{state: string, label: string, tone: string},
     *     counts: array{features: int, permissions: int, opsModules: int, entryPoints: int},
     *     features: list<array{name: string, label: string, description: ?string}>,
     *     permissions: list<array{name: string, label: string, description: ?string}>,
     *     opsModules: list<array{key: string, label: string, type: string, permissionHint: ?string}>,
     *     entryPoints: list<array{label: string, permissionHint: ?string, url: ?string}>
     * }
     */
    public array $details = [
        'metadata' => [
            'name' => '',
            'vendor' => '',
            'description' => '',
            'packageClass' => '',
            'enabled' => false,
            'priority' => null,
        ],
        'posture' => [
            'state' => 'limited',
            'label' => 'Limited',
            'tone' => 'warning',
        ],
        'counts' => [
            'features' => 0,
            'permissions' => 0,
            'opsModules' => 0,
            'entryPoints' => 0,
        ],
        'features' => [],
        'permissions' => [],
        'opsModules' => [],
        'entryPoints' => [],
    ];

    public function mount(): void
    {
        $this->details = app(OpsPackageDetailsResolver::class)->resolve($this->package);
    }

    public function getTitle(): string
    {
        return $this->details['metadata']['name'] === ''
            ? 'Package details'
            : $this->details['metadata']['name'];
    }

    public function getHeading(): string
    {
        return 'Package details';
    }

    public function getSubheading(): ?string
    {
        return $this->details['metadata']['description'] !== ''
            ? $this->details['metadata']['description']
            : null;
    }

    public function packageDetailsInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->details)
            ->components([
                Section::make('Package summary')
                    ->schema([
                        TextEntry::make('metadata.name')
                            ->label('Package'),
                        TextEntry::make('metadata.vendor')
                            ->label('Vendor')
                            ->badge(),
                        TextEntry::make('posture.label')
                            ->label('Posture')
                            ->badge()
                            ->color(fn (): string => $this->details['posture']['tone']),
                        IconEntry::make('metadata.enabled')
                            ->label('Enabled')
                            ->boolean(),
                        TextEntry::make('metadata.priority')
                            ->label('Priority')
                            ->placeholder('n/a'),
                        TextEntry::make('metadata.packageClass')
                            ->label('Package class')
                            ->copyable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Contribution counts')
                    ->schema([
                        TextEntry::make('counts.features')
                            ->label('Features')
                            ->numeric(),
                        TextEntry::make('counts.permissions')
                            ->label('Permissions')
                            ->numeric(),
                        TextEntry::make('counts.opsModules')
                            ->label('Ops modules')
                            ->numeric(),
                        TextEntry::make('counts.entryPoints')
                            ->label('Entry points')
                            ->numeric(),
                    ])
                    ->columns(4),
                Section::make('Features')
                    ->schema([
                        RepeatableEntry::make('features')
                            ->hiddenLabel()
                            ->contained(false)
                            ->table([
                                TableColumn::make('Label'),
                                TableColumn::make('Name'),
                                TableColumn::make('Description')->wrapHeader(),
                            ])
                            ->schema([
                                TextEntry::make('label'),
                                TextEntry::make('name'),
                                TextEntry::make('description')
                                    ->placeholder('No description registered.'),
                            ])
                            ->placeholder('No registered platform features are currently exposed by this package.'),
                    ]),
                Section::make('Permissions')
                    ->schema([
                        RepeatableEntry::make('permissions')
                            ->hiddenLabel()
                            ->contained(false)
                            ->table([
                                TableColumn::make('Label'),
                                TableColumn::make('Name'),
                                TableColumn::make('Description')->wrapHeader(),
                            ])
                            ->schema([
                                TextEntry::make('label'),
                                TextEntry::make('name'),
                                TextEntry::make('description')
                                    ->placeholder('No description registered.'),
                            ])
                            ->placeholder('No package-owned permissions are currently declared for this package.'),
                    ]),
                Section::make('Ops modules')
                    ->schema([
                        RepeatableEntry::make('opsModules')
                            ->hiddenLabel()
                            ->contained(false)
                            ->table([
                                TableColumn::make('Label'),
                                TableColumn::make('Key'),
                                TableColumn::make('Type'),
                                TableColumn::make('Permission hint')->wrapHeader(),
                            ])
                            ->schema([
                                TextEntry::make('label'),
                                TextEntry::make('key'),
                                TextEntry::make('type')
                                    ->badge(),
                                TextEntry::make('permissionHint')
                                    ->placeholder('No permission hint.'),
                            ])
                            ->placeholder('No ops modules are currently declared by this package.'),
                    ]),
                Section::make('Entry points')
                    ->schema([
                        RepeatableEntry::make('entryPoints')
                            ->hiddenLabel()
                            ->contained(false)
                            ->table([
                                TableColumn::make('Label'),
                                TableColumn::make('Permission hint')->wrapHeader(),
                                TableColumn::make('Direct URL')->wrapHeader(),
                            ])
                            ->schema([
                                TextEntry::make('label'),
                                TextEntry::make('permissionHint')
                                    ->placeholder('No permission hint.'),
                                TextEntry::make('url')
                                    ->label('Direct URL')
                                    ->placeholder('No direct URL exposed by the shared module model.'),
                            ])
                            ->placeholder('No package-specific entry points are currently exposed through ops navigation.'),
                    ]),
            ]);
    }
}
