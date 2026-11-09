<?php

declare(strict_types=1);

namespace App\Filament\Resources\Families\RelationManagers;

use App\Admin\FamilyFigures;
use App\Filament\Resources\Families\FamilyResource;
use App\Http\Middleware\LimitVisit;
use App\Models\Family;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

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
                    ->url(static fn (User $record): string => URL::temporarySignedRoute('support.visit.start', now()->addMinutes(LimitVisit::LINK_MINUTES), ['user' => $record->id]))
                    ->openUrlInNewTab(),
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
