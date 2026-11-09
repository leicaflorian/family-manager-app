<?php

declare(strict_types=1);

namespace App\Filament\Resources\Families\Pages;

use App\Admin\FamilyFigure;
use App\Admin\FamilyFigures;
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
                    $this->refreshFormData(['reading_limit']);
                }),
        ];
    }
}
