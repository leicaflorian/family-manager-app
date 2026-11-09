<?php

declare(strict_types=1);

namespace App\Filament\Resources\Families\Pages;

use App\Admin\FamilyFigure;
use App\Admin\FamilyFigures;
use App\Filament\Resources\Families\FamilyResource;
use App\Models\Family;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;

/**
 * The list of the families, with the figures of the page counted all at once.
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
}
