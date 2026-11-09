<?php

declare(strict_types=1);

namespace App\Filament\Resources\Families\RelationManagers;

use App\Filament\Resources\Families\FamilyResource;
use App\Models\OwnerAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The log of what the owner did to a family, newest first, at the bottom of its page.
 */
final class OwnerActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'ownerActions';

    /**
     * The title of the tab.
     *
     * @param  Model  $ownerRecord  the family
     * @param  string  $pageClass  the page showing the tab
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Log');
    }

    /**
     * The table of the log.
     *
     * @param  Table  $table  the table to fill
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('When'))
                    ->state(static fn (OwnerAction $record): string => FamilyResource::dateTime($record->created_at)),
                TextColumn::make('action')->label(__('Action'))
                    ->state(static fn (OwnerAction $record): string => $record->action->label()),
            ])
            ->defaultSort('id', 'desc')
            ->paginated(false);
    }
}
