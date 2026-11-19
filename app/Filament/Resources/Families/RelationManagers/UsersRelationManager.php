<?php

declare(strict_types=1);

namespace App\Filament\Resources\Families\RelationManagers;

use App\Admin\FamilyFigures;
use App\Filament\Resources\Families\FamilyResource;
use App\Mail\WelcomeMail;
use App\Models\Family;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * The accounts of a family: name, email, level, confirmed email and last access, *Enter as…* while the family
 * lets support in, and the welcome mail again for an account that has not confirmed its email.
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
                    // A route of the panel: the one-time link of the app lasts one minute, so it is made on the click
                    ->url(static fn (User $record): string => URL::signedRoute('filament.admin.enter-as', ['user' => $record->id]))
                    ->openUrlInNewTab(),
                // The link of the welcome mail works until the email is confirmed: a new one lasts another week
                Action::make('resendWelcome')->label(__('Send the welcome email again'))->icon('heroicon-o-envelope')
                    ->visible(static fn (User $record): bool => $record->email_verified_at === null)
                    ->requiresConfirmation()
                    ->modalHeading(__('Send the welcome email again?'))
                    ->modalDescription(static fn (User $record): string => __('We send :email a new link to choose the password.', ['email' => $record->email]))
                    ->action(static function (User $record): void {
                        Mail::to($record)->locale(app()->getLocale())->send(new WelcomeMail($record));
                        Notification::make()->title(__('Sent'))->success()->send();
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
