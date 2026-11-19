<?php

declare(strict_types=1);

namespace App\Filament\Resources\Families\Pages;

use App\Actions\Admin\CreateFamilyFromPanel;
use App\Actions\Admin\SendWelcomeMail;
use App\Admin\FamilyFigure;
use App\Admin\FamilyFigures;
use App\Filament\Resources\Families\FamilyResource;
use App\Models\Family;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Collection;

/**
 * The list of the families, with the figures of the page counted all at once, and *Create a family*.
 */
final class ListFamilies extends ListRecords
{
    protected static string $resource = FamilyResource::class;

    /** @var array<int, FamilyFigure> the figures of the families of the page, by family id; rebuilt on each request */
    protected array $figures = [];

    /**
     * The figures of a family of the page. The first call counts the whole page in one go.
     *
     * @param  Family  $family  a family of the table
     */
    public function figureOf(Family $family): FamilyFigure
    {
        if (! isset($this->figures[$family->id])) {
            $ids = [];
            $records = $this->getTableRecords();
            foreach ($records instanceof Collection ? $records->all() : $records->items() as $record) {
                if ($record instanceof Family) {
                    $ids[] = $record->id;
                }
            }

            $this->figures += resolve(FamilyFigures::class)->forFamilies($ids, CarbonImmutable::now());
        }

        return $this->figures[$family->id] ?? new FamilyFigure;
    }

    /**
     * *Create a family*: the registration is closed, so a new family enters from here, with its first account and
     * the welcome mail that lets it choose the password.
     *
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createFamily')
                ->label(__('Create a family'))
                ->icon('heroicon-o-plus')
                ->modalHeading(__('Create a family'))
                ->modalDescription(__('We send the first account an email with the link to choose the password.'))
                ->modalSubmitActionLabel(__('Create'))
                ->schema([
                    TextInput::make('first_name')->label(__('First name'))->required()->maxLength(255),
                    TextInput::make('last_name')->label(__('Last name'))->required()->maxLength(255)
                        ->live(onBlur: true)
                        // The family takes the last name, until its name is written by hand
                        ->afterStateUpdated(static function (Get $get, Set $set, ?string $old, ?string $state): void {
                            $family = $get('family_name');
                            if (blank($family) || $family === $old) {
                                $set('family_name', $state);
                            }
                        }),
                    TextInput::make('family_name')->label(__('Name of the family'))->required()->maxLength(255),
                    TextInput::make('email')->label(__('Email'))->required()->email()->maxLength(255)
                        ->unique(User::class, 'email'),
                    ToggleButtons::make('locale')->label(__('Language of the family and of the email'))
                        ->options(['it' => 'Italiano', 'en' => 'English'])
                        ->in(['it', 'en'])
                        ->default('it')
                        ->required()
                        ->inline(),
                ])
                ->action(function (array $data): void {
                    /** @var array{first_name: string, last_name: string, family_name: string, email: string, locale: string} $data */
                    $user = resolve(CreateFamilyFromPanel::class)->handle($data['family_name'], $data['first_name'], $data['last_name'], $data['email'], $data['locale']);

                    // The family stays even when the mail does not go out: the owner sends it again from its page
                    if (resolve(SendWelcomeMail::class)->handle($user)) {
                        Notification::make()
                            ->title(__('Family created. We sent :email the link to choose the password.', ['email' => $user->email]))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('Family created, but the email did not go out. Send it again from the Accounts tab of the family.'))
                            ->warning()
                            ->persistent()
                            ->send();
                    }

                    $this->redirect(FamilyResource::getUrl('view', ['record' => $user->family_id]));
                }),
        ];
    }
}
