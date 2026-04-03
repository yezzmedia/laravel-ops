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
use YezzMedia\Ops\Support\OpsRoleDetailsResolver;

final class RoleDetailsPage extends OpsPage
{
    protected static string $opsSurface = 'access_management';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $slug = 'access/manage/details';

    protected string $view = 'ops::pages.role-details-page';

    #[Url]
    public string $role = '';

    /**
     * @var array{summary: array{name: string, permissionCount: int, assignmentCount: int, isSuperAdminRole: bool, superAdminStatus: string}, permissionNames: list<string>, permissionNamesLabel: string}
     */
    public array $details = [
        'summary' => [
            'name' => '',
            'permissionCount' => 0,
            'assignmentCount' => 0,
            'isSuperAdminRole' => false,
            'superAdminStatus' => 'Standard',
        ],
        'permissionNames' => [],
        'permissionNamesLabel' => 'n/a',
    ];

    public function mount(): void
    {
        $this->details = app(OpsRoleDetailsResolver::class)->resolve($this->role);
    }

    public function getTitle(): string
    {
        return $this->details['summary']['name'] === '' ? 'Role details' : $this->details['summary']['name'];
    }

    public function getHeading(): string
    {
        return 'Role details';
    }

    public function getSubheading(): ?string
    {
        return $this->details['summary']['isSuperAdminRole'] ? 'Super-admin role' : 'Persisted role';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToAccessManagement')
                ->label('Back to access management')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(AccessManagementPage::getUrl(panel: (string) config('ops.panel.id', 'ops'))),
        ];
    }

    public function roleDetailsInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->details)
            ->components([
                Section::make('Role summary')
                    ->schema([
                        TextEntry::make('summary.name')->label('Role')->copyable(),
                        TextEntry::make('summary.permissionCount')->label('Permissions')->numeric(),
                        TextEntry::make('summary.assignmentCount')->label('Assignments')->numeric(),
                        TextEntry::make('summary.superAdminStatus')->label('Posture')->badge(),
                    ])
                    ->columns(2),
                Section::make('Assigned permissions')->schema([
                    TextEntry::make('permissionNamesLabel')
                        ->label('Permission names')
                        ->placeholder('This role currently has no permissions assigned.')
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
