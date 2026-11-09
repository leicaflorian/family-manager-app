<?php

declare(strict_types=1);

namespace App\Filament\Resources\Families\RelationManagers;

use App\Admin\FamilyDevices;
use App\Filament\Resources\Families\FamilyResource;
use App\Models\Family;
use App\Models\LinkedDevice;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The phones linked to the accounts of a family: name and last use.
 */
final class LinkedDevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    /**
     * The title of the tab.
     *
     * @param  Model  $ownerRecord  the family
     * @param  string  $pageClass  the page showing the tab
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Linked phones');
    }

    /**
     * The table of the phones.
     *
     * @param  Table  $table  the table to fill
     */
    public function table(Table $table): Table
    {
        $family = $this->getOwnerRecord();
        assert($family instanceof Family);

        return $table
            ->query(static fn (): Builder => FamilyDevices::of($family))
            ->columns([
                TextColumn::make('name')->label(__('Name')),
                TextColumn::make('user.name')->label(__('Account')),
                TextColumn::make('last_used_at')->label(__('Last use'))
                    ->state(static fn (LinkedDevice $record): string => FamilyResource::dateTime($record->last_used_at)),
            ])
            ->paginated(false);
    }
}
