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
     *     summary: array{id: string, description: string, event: string, logName: string, loggedAt: string, actorLabel: string, subjectLabel: string, contextPreview: ?string, contextJson: string, changesJson: string, backend: string, statusLabel: string, statusTone: string, sourceLabel: string, cachedAt: ?string},
     *     contextRows: list<array{key: string, valuePreview: string, valueRaw: string}>,
     *     changesRows: list<array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string, removedSegment: ?string, addedSegment: ?string}>
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
            'contextJson' => '[]',
            'changesJson' => '[]',
            'backend' => 'Unavailable',
            'statusLabel' => 'Unavailable',
            'statusTone' => 'gray',
            'sourceLabel' => 'fresh read',
            'cachedAt' => null,
        ],
        'contextRows' => [],
        'changesRows' => [],
    ];

    public function mount(): void
    {
        $this->details = app(OpsAuditEntryDetailsResolver::class)->resolve($this->entry);
        $this->details['changesRows'] = array_map(
            static fn (array $row): array => self::decorateChangeRow($row),
            $this->details['changesRows'],
        );
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
                        TextEntry::make('summary.sourceLabel')
                            ->label('Snapshot source')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('summary.cachedAt')
                            ->label('Snapshot refreshed at'),
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
                        TextEntry::make('summary.contextJson')
                            ->label('Raw context JSON')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('summary.changesJson')
                            ->label('Raw changes JSON')
                            ->copyable()
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
                                TableColumn::make('Removed'),
                                TableColumn::make('Added'),
                                TableColumn::make('New value'),
                            ])
                            ->schema([
                                TextEntry::make('field'),
                                TextEntry::make('oldPreview')
                                    ->label('Old value')
                                    ->tooltip(fn (Get $get): ?string => $get('oldRaw'))
                                    ->copyable(),
                                TextEntry::make('removedSegment')
                                    ->label('Removed')
                                    ->badge()
                                    ->color('danger')
                                    ->placeholder('No removal'),
                                TextEntry::make('addedSegment')
                                    ->label('Added')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('No addition'),
                                TextEntry::make('newPreview')
                                    ->label('New value')
                                    ->tooltip(fn (Get $get): ?string => $get('newRaw'))
                                    ->copyable(),
                            ])
                            ->placeholder('This audit entry did not expose tracked attribute changes.'),
                    ]),
            ]);
    }

    /**
     * @param  array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string}  $row
     * @return array{field: string, oldPreview: string, oldRaw: string, newPreview: string, newRaw: string, removedSegment: ?string, addedSegment: ?string}
     */
    private static function decorateChangeRow(array $row): array
    {
        return [
            ...$row,
            'removedSegment' => self::diffSegment($row['oldRaw'], $row['newRaw'], 'old'),
            'addedSegment' => self::diffSegment($row['newRaw'], $row['oldRaw'], 'new'),
        ];
    }

    private static function diffSegment(string $value, string $comparison, string $tone): ?string
    {
        if ($value === $comparison) {
            return null;
        }

        [, $segment] = match ($tone) {
            'old' => self::splitDiff($value, $comparison),
            'new' => self::splitDiff($value, $comparison),
            default => self::splitDiff($value, $comparison),
        };

        return $segment !== '' ? $segment : null;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function splitDiff(string $value, string $comparison): array
    {
        $prefixLength = 0;
        $maxPrefixLength = min(strlen($value), strlen($comparison));

        while ($prefixLength < $maxPrefixLength && $value[$prefixLength] === $comparison[$prefixLength]) {
            $prefixLength++;
        }

        $valueSuffixIndex = strlen($value) - 1;
        $comparisonSuffixIndex = strlen($comparison) - 1;
        $suffixLength = 0;

        while (
            $valueSuffixIndex - $suffixLength >= $prefixLength
            && $comparisonSuffixIndex - $suffixLength >= $prefixLength
            && $value[$valueSuffixIndex - $suffixLength] === $comparison[$comparisonSuffixIndex - $suffixLength]
        ) {
            $suffixLength++;
        }

        $prefix = substr($value, 0, $prefixLength);
        $middleLength = strlen($value) - $prefixLength - $suffixLength;
        $middle = $middleLength > 0 ? substr($value, $prefixLength, $middleLength) : '';
        $suffix = $suffixLength > 0 ? substr($value, -$suffixLength) : '';

        return [$prefix, $middle, $suffix];
    }
}
