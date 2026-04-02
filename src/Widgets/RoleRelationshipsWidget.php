<?php

declare(strict_types=1);

namespace YezzMedia\Ops\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Surfaces persisted role-to-permission relationships for permission review.
 */
class RoleRelationshipsWidget extends TableWidget
{
    /**
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * @var array{installed: bool, available: bool, error: ?string, store: array{configPublished: bool, migrationsPublished: bool, pendingMigrations: bool, ready: bool}, permissions: list<array{name: string, package: string, label: string, synced: bool, roleHints: list<string>, assignedRoles: list<string>}>, roles: list<array{name: string, permissionNames: list<string>}>}
     */
    public array $overview = [
        'installed' => false,
        'available' => false,
        'error' => null,
        'store' => [
            'configPublished' => false,
            'migrationsPublished' => false,
            'pendingMigrations' => false,
            'ready' => false,
        ],
        'permissions' => [],
        'roles' => [],
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Role relationships')
            ->description('Persisted role-to-permission mappings currently available from the access runtime.')
            ->records(fn (): Collection => collect($this->overview['roles'])
                ->map(static function (array $role): array {
                    return [
                        'name' => $role['name'],
                        'permissionNamesLabel' => $role['permissionNames'] === []
                            ? 'No permissions are currently assigned.'
                            : implode(', ', $role['permissionNames']),
                    ];
                }))
            ->columns([
                TextColumn::make('name')
                    ->label('Role')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('permissionNamesLabel')
                    ->label('Permission names')
                    ->wrap(),
            ])
            ->emptyStateHeading('No persisted role relationships are currently available.');
    }
}
