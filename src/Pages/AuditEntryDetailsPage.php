<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use YezzMedia\Ops\Support\OpsAuditEntryDetailsResolver;

/**
 * Surfaces one audit entry in a read-only drilldown.
 */
final class AuditEntryDetailsPage extends OpsPage
{
    protected static string $opsSurface = 'audit';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $slug = 'audit/details';

    protected string $view = 'ops::pages.audit-entry-details-page';

    #[Url]
    public string $entry = '';

    /**
     * @var array{
     *     summary: array{id: string, description: string, event: string, logName: string, loggedAt: string, actorLabel: string, subjectLabel: string, contextPreview: ?string, backend: string, statusLabel: string, statusTone: string},
     *     contextRows: list<array{key: string, valuePreview: string, valueRaw: string}>,
     *     changesRows: list<array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string}>
     * }
     */
    public array $details = [
        'summary' => [
            'id' => '',
            'description' => '',
            'event' => 'n/a',
            'logName' => 'n/a',
            'loggedAt' => 'n/a',
            'actorLabel' => 'System',
            'subjectLabel' => 'Unknown subject',
            'contextPreview' => null,
            'backend' => 'Unavailable',
            'statusLabel' => 'Unavailable',
            'statusTone' => 'gray',
        ],
        'contextRows' => [],
        'changesRows' => [],
    ];

    public function mount(): void
    {
        $this->details = app(OpsAuditEntryDetailsResolver::class)->resolve($this->entry);
    }

    public function getTitle(): string
    {
        return 'Audit entry details';
    }

    public function getHeading(): string
    {
        return 'Audit entry details';
    }

    public function getSubheading(): ?string
    {
        return $this->details['summary']['description'] !== '' ? $this->details['summary']['description'] : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToAudit')
                ->label('Back to audit')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(AuditTrailPage::getUrl(panel: (string) config('ops.panel.id', 'ops'))),
        ];
    }

    public function auditEntryDetailsInfolist(Schema $schema): Schema
    {
        return $schema
            ->state($this->details)
            ->components([
                Section::make('Entry summary')
                    ->schema([
                        TextEntry::make('summary.statusLabel')
                            ->label('Status')
                            ->badge()
                            ->color(fn (): string => $this->details['summary']['statusTone']),
                        TextEntry::make('summary.backend')
                            ->label('Backend')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('summary.loggedAt')
                            ->label('Logged at'),
                        TextEntry::make('summary.event')
                            ->label('Event')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('summary.logName')
                            ->label('Log')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('summary.id')
                            ->label('Entry id')
                            ->copyable(),
                        TextEntry::make('summary.actorLabel')
                            ->label('Actor')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('summary.subjectLabel')
                            ->label('Subject')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('summary.contextPreview')
                            ->label('Context preview')
                            ->placeholder('No context preview is available.')
                            ->columnSpanFull(),
                        TextEntry::make('summary.description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Context properties')
                    ->schema([
                        RepeatableEntry::make('contextRows')
                            ->hiddenLabel()
                            ->contained(false)
                            ->table([
                                TableColumn::make('Key'),
                                TableColumn::make('Value'),
                            ])
                            ->schema([
                                TextEntry::make('key'),
                                TextEntry::make('valuePreview')
                                    ->tooltip(fn (Get $get): ?string => $get('valueRaw'))
                                    ->copyable(),
                            ])
                            ->placeholder('This audit entry did not expose additional context properties.'),
                    ]),
                Section::make('Attribute changes')
                    ->schema([
                        RepeatableEntry::make('changesRows')
                            ->hiddenLabel()
                            ->contained(false)
                            ->table([
                                TableColumn::make('Field'),
                                TableColumn::make('Old value'),
                                TableColumn::make('New value'),
                            ])
                            ->schema([
                                TextEntry::make('field'),
                                TextEntry::make('oldPreview')
                                    ->label('Old value')
                                    ->tooltip(fn (Get $get): ?string => $get('oldRaw')),
                                TextEntry::make('newPreview')
                                    ->label('New value')
                                    ->tooltip(fn (Get $get): ?string => $get('newRaw')),
                            ])
                            ->placeholder('This audit entry did not expose tracked attribute changes.'),
                    ]),
            ]);
    }
}
