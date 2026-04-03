<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use YezzMedia\Ops\Support\OpsPermissionDetailsResolver;

/**
 * Surfaces one declared permission in a read-only drilldown.
 */
final class PermissionDetailsPage extends OpsPage
{
    protected static string $opsSurface = 'permissions';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $slug = 'access/permissions/details';

    protected string $view = 'ops::pages.permission-details-page';

    #[Url]
    public string $permission = '';

    /**
     * @var array{
     *     summary: array{name: string, label: string, package: string, packageDescription: ?string, description: ?string, syncedLabel: string, syncedTone: string, roleHintsCount: int, assignedRoleCount: int},
     *     roleHints: list<string>,
     *     roleHintsLabel: string,
     *     assignedRoles: list<string>,
     *     assignedRolesLabel: string
     * }
     */
    public array $details = [
        'summary' => [
            'name' => '',
            'label' => '',
            'package' => '',
            'packageDescription' => null,
            'description' => null,
            'syncedLabel' => 'Not synced',
            'syncedTone' => 'warning',
            'roleHintsCount' => 0,
            'assignedRoleCount' => 0,
        ],
        'roleHints' => [],
        'roleHintsLabel' => 'n/a',
        'assignedRoles' => [],
        'assignedRolesLabel' => 'n/a',
    ];

    public function mount(): void
    {
        $this->details = app(OpsPermissionDetailsResolver::class)->resolve($this->permission);
    }

    public function getTitle(): string
    {
        return $this->details['summary']['name'] === ''
            ? 'Permission details'
            : $this->details['summary']['name'];
    }

    public function getHeading(): string
    {
        return 'Permission details';
    }

    public function getSubheading(): ?string
    {
        return $this->details['summary']['label'] !== ''
            ? $this->details['summary']['label']
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToPermissions')
                ->label('Back to permissions')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(PermissionsPage::getUrl(panel: (string) config('ops.panel.id', 'ops'))),
        ];
    }

    public function permissionDetailsInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->details)
            ->components([
                Section::make('Permission summary')
                    ->schema([
                        TextEntry::make('summary.name')->label('Permission')->copyable(),
                        TextEntry::make('summary.label')->label('Label'),
                        TextEntry::make('summary.package')->label('Package')->badge(),
                        TextEntry::make('summary.syncedLabel')->label('Sync status')->badge()->color(fn (): string => $this->details['summary']['syncedTone']),
                        TextEntry::make('summary.packageDescription')->label('Package description')->placeholder('No package description registered.'),
                        TextEntry::make('summary.description')->label('Permission description')->placeholder('No permission description registered.')->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Role hints')->schema([
                    TextEntry::make('summary.roleHintsCount')->label('Hint count')->numeric(),
                    TextEntry::make('roleHintsLabel')
                        ->label('Role hints')
                        ->placeholder('No default role hints are currently registered.')
                        ->columnSpanFull(),
                ]),
                Section::make('Assigned roles')->schema([
                    TextEntry::make('summary.assignedRoleCount')->label('Assigned role count')->numeric(),
                    TextEntry::make('assignedRolesLabel')
                        ->label('Assigned roles')
                        ->placeholder('No roles are currently assigned to this permission.')
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
