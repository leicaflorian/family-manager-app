<?php

declare(strict_types=1);

namespace App\Http\Requests\Budget;

use App\Http\Requests\Concerns\ReadsSearchFilters;
use App\Support\PlannedFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The address of *Planned expenses*: `?search=luce&min=20&max=100&from=2026-11-01&to=2026-11-30`. The filters are
 * typed by the person, so a wrong one is an error to correct (422).
 */
final class PlannedIndexRequest extends FormRequest
{
    use ReadsSearchFilters;

    /**
     * The rules of the filters.
     *
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    public function rules(): array
    {
        return $this->searchFilterRules();
    }

    /**
     * The filters asked: the text trimmed (null when empty), the amounts and the next due dates.
     */
    public function filters(): PlannedFilters
    {
        return new PlannedFilters(
            search: $this->searchText(),
            min: $this->filterAmount('min'),
            max: $this->filterAmount('max'),
            from: $this->filterDay('from'),
            to: $this->filterDay('to'),
        );
    }
}
