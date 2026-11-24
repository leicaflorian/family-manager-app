<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Enums\SearchGroup;
use App\Services\FamilyFeatures;
use App\Support\SearchTerms;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The address of the global search: `?q=text&type=group`.
 */
final class SearchRequest extends FormRequest
{
    /**
     * The rules of the address.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            // *Movements* and *Dues* belong to the Budget: with it off they are not groups to ask for
            'type' => ['nullable', Rule::enum(SearchGroup::class)->unless(
                resolve(FamilyFeatures::class)->on(Feature::Budget),
                static fn ($rule) => $rule->except([SearchGroup::Movements, SearchGroup::Dues]),
            )],
        ];
    }

    /**
     * What the person typed, read.
     */
    public function terms(): SearchTerms
    {
        return SearchTerms::fromQuery($this->string('q')->toString());
    }

    /**
     * The group the search is narrowed to, or null for all.
     */
    public function group(): ?SearchGroup
    {
        return SearchGroup::tryFrom($this->string('type')->toString());
    }
}
