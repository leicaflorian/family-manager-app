<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * For the requests of a list with a search box and filters typed by the person (`?search=…&min=20&max=100&from=…&to=…`):
 * the rules of those fields and their values read back. A mistake in them is an error to correct (422).
 */
trait ReadsSearchFilters
{
    /** The fields typed by the person. */
    public const array FILTER_FIELDS = ['search', 'min', 'max', 'from', 'to'];

    /**
     * The rules of the search and of the ranges of amounts and days: the largest amount not under the smallest, the
     * last day not before the first.
     *
     * @return array<string, array<mixed>|ValidationRule|string>
     */
    protected function searchFilterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'min' => ['nullable', 'numeric', 'min:0'],
            'max' => ['nullable', 'numeric', 'min:0', Rule::when($this->filled('min'), 'gte:min')],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
        ];
    }

    /**
     * The text searched, trimmed; null when it is empty or only spaces.
     */
    protected function searchText(): ?string
    {
        $text = trim($this->string('search')->toString());

        return $text === '' ? null : $text;
    }

    /**
     * An amount of the filters, null when it is not given.
     *
     * @param  'min'|'max'  $field  the field
     */
    protected function filterAmount(string $field): ?float
    {
        return $this->filled($field) ? $this->float($field) : null;
    }

    /**
     * A day of the filters, `yyyy-mm-dd`, null when it is not given.
     *
     * @param  'from'|'to'  $field  the field
     */
    protected function filterDay(string $field): ?string
    {
        return $this->filled($field) ? $this->string($field)->toString() : null;
    }
}
