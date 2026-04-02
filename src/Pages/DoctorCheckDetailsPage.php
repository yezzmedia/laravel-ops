<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use YezzMedia\Ops\Support\OpsDoctorCheckDetailsResolver;

/**
 * Surfaces one diagnostics doctor check in a read-only drilldown.
 */
final class DoctorCheckDetailsPage extends OpsPage
{
    protected static string $opsSurface = 'diagnostics';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $slug = 'diagnostics/details';

    protected string $view = 'ops::pages.doctor-check-details-page';

    #[Url]
    public string $package = '';

    #[Url]
    public string $check = '';

    /**
     * @var array{
     *     summary: array{key: string, package: string, status: string, statusLabel: string, statusTone: string, message: string, isBlocking: bool, blockingLabel: string},
     *     snapshot: array{completedAt: string, accessMode: string, diagnosticsStatus: string, healthInstalled: bool, auditInstalled: bool},
     *     insights: array{missingPermissions: list<array{value: string}>, extraPermissions: list<array{value: string}>, declaredPermissionsCount: int|null, persistedPermissionsCount: int|null, roleName: ?string, exception: ?string, exceptionMessage: ?string},
     *     rawContextRows: list<array{key: string, valuePreview: string, valueRaw: string}>
     * }
     */
    public array $details = [
        'summary' => [
            'key' => '',
            'package' => '',
            'status' => 'skipped',
            'statusLabel' => 'Skipped',
            'statusTone' => 'gray',
            'message' => '',
            'isBlocking' => false,
            'blockingLabel' => 'Non-blocking',
        ],
        'snapshot' => [
            'completedAt' => '',
            'accessMode' => '',
            'diagnosticsStatus' => '',
            'healthInstalled' => false,
            'auditInstalled' => false,
        ],
        'insights' => [
            'missingPermissions' => [],
            'extraPermissions' => [],
            'declaredPermissionsCount' => null,
            'persistedPermissionsCount' => null,
            'roleName' => null,
            'exception' => null,
            'exceptionMessage' => null,
        ],
        'rawContextRows' => [],
    ];

    public function mount(): void
    {
        $this->details = app(OpsDoctorCheckDetailsResolver::class)->resolve($this->package, $this->check);
    }

    public function getTitle(): string
    {
        return $this->details['summary']['key'] === ''
            ? 'Doctor check details'
            : $this->details['summary']['key'];
    }

    public function getHeading(): string
    {
        return 'Doctor check details';
    }

    public function getSubheading(): ?string
    {
        return $this->details['summary']['message'] !== ''
            ? $this->details['summary']['message']
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToDiagnostics')
                ->label('Back to diagnostics')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(SystemHealthPage::getUrl(panel: (string) config('ops.panel.id', 'ops'))),
        ];
    }

    public function doctorCheckDetailsInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->details)
            ->components([
                Section::make('Check summary')
                    ->schema([
                        TextEntry::make('summary.key')
                            ->label('Check key')
                            ->copyable(),
                        TextEntry::make('summary.package')
                            ->label('Package')
                            ->badge(),
                        TextEntry::make('summary.statusLabel')
                            ->label('Status')
                            ->badge()
                            ->color(fn (): string => $this->details['summary']['statusTone']),
                        TextEntry::make('summary.blockingLabel')
                            ->label('Blocking')
                            ->badge()
                            ->color(fn (): string => $this->details['summary']['isBlocking'] ? 'danger' : 'gray'),
                        TextEntry::make('summary.message')
                            ->label('Message')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Diagnostics snapshot')
                    ->schema([
                        TextEntry::make('snapshot.completedAt')
                            ->label('Completed at')
                            ->placeholder('n/a'),
                        TextEntry::make('snapshot.diagnosticsStatus')
                            ->label('Diagnostics status')
                            ->badge(),
                        TextEntry::make('snapshot.accessMode')
                            ->label('Access mode')
                            ->badge(),
                        TextEntry::make('snapshot.healthInstalled')
                            ->label('Health backend')
                            ->formatStateUsing(static fn (bool $state): string => $state ? 'Installed' : 'Unavailable')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('snapshot.auditInstalled')
                            ->label('Audit backend')
                            ->formatStateUsing(static fn (bool $state): string => $state ? 'Installed' : 'Unavailable')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                    ])
                    ->columns(3),
                Section::make('Context insights')
                    ->schema([
                        TextEntry::make('insights.roleName')
                            ->label('Role name')
                            ->placeholder('No role-specific context.'),
                        TextEntry::make('insights.declaredPermissionsCount')
                            ->label('Declared permissions')
                            ->numeric()
                            ->placeholder('n/a'),
                        TextEntry::make('insights.persistedPermissionsCount')
                            ->label('Persisted permissions')
                            ->numeric()
                            ->placeholder('n/a'),
                        TextEntry::make('insights.exception')
                            ->label('Exception')
                            ->placeholder('No exception recorded.'),
                        TextEntry::make('insights.exceptionMessage')
                            ->label('Exception message')
                            ->placeholder('No exception message recorded.')
                            ->columnSpanFull(),
                        RepeatableEntry::make('insights.missingPermissions')
                            ->label('Missing permissions')
                            ->contained(false)
                            ->table([
                                TableColumn::make('Permission'),
                            ])
                            ->schema([
                                TextEntry::make('value'),
                            ])
                            ->placeholder('No missing permissions were recorded.'),
                        RepeatableEntry::make('insights.extraPermissions')
                            ->label('Extra permissions')
                            ->contained(false)
                            ->table([
                                TableColumn::make('Permission'),
                            ])
                            ->schema([
                                TextEntry::make('value'),
                            ])
                            ->placeholder('No extra permissions were recorded.'),
                    ])
                    ->columns(3),
                Section::make('Raw context')
                    ->schema([
                        RepeatableEntry::make('rawContextRows')
                            ->hiddenLabel()
                            ->contained(false)
                            ->table([
                                TableColumn::make('Key'),
                                TableColumn::make('Value'),
                            ])
                            ->schema([
                                TextEntry::make('key'),
                                TextEntry::make('valuePreview')
                                    ->tooltip(fn (TextEntry $component): ?string => data_get($component->getContainer()->getState(), 'valueRaw'))
                                    ->copyable(),
                            ])
                            ->placeholder('This doctor check did not expose additional raw context.'),
                    ]),
            ]);
    }
}
