<?php

declare(strict_types=1);

namespace App\Filament\Resources\Families\RelationManagers;

use App\Admin\FamilyFigures;
use App\Filament\Resources\Families\FamilyResource;
use App\Models\Family;
use App\Models\Owner;
use App\Models\User;
use App\Support\VisitHandoff;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The accounts of a family: name, email, level, confirmed email and last access, and *Enter as…* while the family
 * lets support in.
 */
final class UsersRelationManager extends RelationManager
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
        return __('Accounts');
    }

    /**
     * The table of the accounts.
     *
     * @param  Table  $table  the table to fill
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name')),
                TextColumn::make('email')->label(__('Email')),
                TextColumn::make('role')->label(__('Level'))->state(static fn (User $record): string => $record->role->label()),
                IconColumn::make('email_verified_at')->label(__('Email confirmed'))->boolean()
                    ->state(static fn (User $record): bool => $record->email_verified_at !== null),
                TextColumn::make('last_activity')->label(__('Last access'))
                    ->state(static fn (User $record): string => FamilyResource::dateTime(resolve(FamilyFigures::class)->lastActivityOf($record->id))),
            ])
            ->recordActions([
                // Only while the family lets support in; the visit checks it again when it starts
                Action::make('enterAs')->label(__('Enter as'))->icon('heroicon-o-eye')
                    ->visible(fn (): bool => $this->family()->allowsSupport())
                    // The link lasts one minute and works once, so it is made on the click, not when the table is drawn
                    ->action(static function (User $record, RelationManager $livewire): void {
                        $owner = Filament::auth()->user();
                        assert($owner instanceof Owner);

                        $link = json_encode(VisitHandoff::link($owner, $record), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
                        $livewire->js('window.open('.$link.', "_blank", "noopener")');
                    }),
            ])
            ->paginated(false);
    }

    /**
     * The family of the page.
     */
    private function family(): Family
    {
        $family = $this->getOwnerRecord();

        assert($family instanceof Family);

        return $family;
    }
}
