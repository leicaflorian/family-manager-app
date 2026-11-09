<?php

declare(strict_types=1);

namespace App\Filament\Resources\Families\Pages;

use App\Actions\Admin\CancelFamilyDeletion;
use App\Actions\Admin\LogOwnerAction;
use App\Actions\Admin\ReactivateFamily;
use App\Actions\Admin\ScheduleFamilyDeletion;
use App\Actions\Admin\SuspendFamily;
use App\Admin\FamilyFigure;
use App\Admin\FamilyFigures;
use App\Enums\FamilyState;
use App\Enums\OwnerActionType;
use App\Filament\Resources\Families\FamilyResource;
use App\Models\Family;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

/**
 * The page of a family: figures, reading limit, accounts and linked phones.
 */
final class ViewFamily extends ViewRecord
{
    protected static string $resource = FamilyResource::class;

    /**
     * The family of the page.
     */
    public function family(): Family
    {
        $family = $this->getRecord();
        assert($family instanceof Family);

        return $family;
    }

    /**
     * Reads the family again after an action changed it, so the state and the buttons follow.
     */
    private function refreshRecord(): void
    {
        $this->family()->refresh();
    }

    /**
     * The figures of this family for the current month.
     */
    public function figure(): FamilyFigure
    {
        $family = $this->getRecord();
        assert($family instanceof Family);

        return resolve(FamilyFigures::class)->forFamilies([$family->id], CarbonImmutable::now())[$family->id] ?? new FamilyFigure;
    }

    /**
     * The readings of the last 12 months, one line per month: `10/2026: 12 readings, 0,0142 $`.
     *
     * @return list<string>
     */
    public function monthLines(): array
    {
        $family = $this->getRecord();
        assert($family instanceof Family);
        $lines = [];

        foreach (resolve(FamilyFigures::class)->monthlyReadings($family->id) as $month => $row) {
            $lines[] = sprintf('%s/%s: %s, %s', substr($month, 5), substr($month, 0, 4), trans_choice('{1} :count reading|[0,*] :count readings', $row['readings']), FamilyResource::dollars($row['cost']));
        }

        return array_reverse($lines);
    }

    /**
     * The actions of the page: the reading limit of the family.
     *
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('editReadingLimit')
                ->label(__('Reading limit of this family'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->fillForm(function (): array {
                    $family = $this->getRecord();
                    assert($family instanceof Family);

                    return ['reading_limit' => $family->reading_limit];
                })
                ->schema([
                    TextInput::make('reading_limit')
                        ->label(__('Reading limit of this family'))
                        ->helperText(__('Readings a month. Empty means the same limit as everyone.'))
                        ->numeric()
                        ->integer()
                        ->minValue(0),
                ])
                ->action(function (array $data): void {
                    $limit = $data['reading_limit'] ?? null;
                    $family = $this->getRecord();
                    assert($family instanceof Family);
                    $family->forceFill(['reading_limit' => is_numeric($limit) ? (int) $limit : null])->save();
                    resolve(LogOwnerAction::class)->handle(OwnerActionType::ReadingLimitChanged, $family);
                    $this->refreshFormData(['reading_limit']);
                }),
            Action::make('suspend')
                ->label(__('Suspend'))
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->visible(fn (): bool => $this->family()->state() === FamilyState::Active)
                ->requiresConfirmation()
                ->modalHeading(__('Suspend this family?'))
                ->modalDescription(__('Nobody of the family can sign in until you reactivate it.'))
                ->action(function (): void {
                    resolve(SuspendFamily::class)->handle($this->family());
                    $this->refreshRecord();
                }),
            Action::make('reactivate')
                ->label(__('Reactivate'))
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->visible(fn (): bool => $this->family()->state() === FamilyState::Suspended)
                ->requiresConfirmation()
                ->modalHeading(__('Reactivate this family?'))
                ->action(function (): void {
                    resolve(ReactivateFamily::class)->handle($this->family());
                    $this->refreshRecord();
                }),
            Action::make('delete')
                ->label(__('Delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => $this->family()->state() !== FamilyState::ToDelete)
                ->modalHeading(__('Delete this family?'))
                ->modalDescription(fn (): string => __('It will be deleted on :date. Until then you can cancel.', [
                    'date' => now('Europe/Rome')->addDays(ScheduleFamilyDeletion::DAYS)->format('d/m/Y'),
                ]))
                ->schema(fn (): array => [
                    TextInput::make('name')
                        ->label(__('Write the name of the family to confirm: :name', ['name' => $this->family()->name]))
                        ->required()
                        ->in([$this->family()->name])
                        ->validationMessages(['in' => __('The name is not the same.')])
                        ->live(),
                ])
                ->action(function (): void {
                    resolve(ScheduleFamilyDeletion::class)->handle($this->family());
                    $this->refreshRecord();
                }),
            Action::make('cancelDeletion')
                ->label(__('Cancel deletion'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (): bool => $this->family()->state() === FamilyState::ToDelete)
                ->requiresConfirmation()
                ->modalHeading(__('Cancel the deletion?'))
                ->action(function (): void {
                    resolve(CancelFamilyDeletion::class)->handle($this->family());
                    $this->refreshRecord();
                }),
        ];
    }
}
