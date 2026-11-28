<?php

declare(strict_types=1);

namespace App\Data\Banks;

use App\Support\Reading\BankFilterSuggestion;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the AI proposes for the form of a bank filter, as the page reads it: the words, and in the fill mode a title,
 * a category and an organization (an organization the family does not have comes back as a new name to confirm).
 */
#[TypeScript]
final class BankFilterSuggestionData extends Data
{
    /**
     * @param  list<string>  $contains  the words the text must all have
     * @param  list<string>  $excludes  the words the text must not have
     * @param  string|null  $title  the title of the expense or income the filter creates
     * @param  string|null  $category  the name of the category of the family
     * @param  int|null  $category_id  its id
     * @param  string|null  $organization  the name of the organization, of the family or new
     * @param  int|null  $organization_id  its id, null for a new one
     * @param  bool  $organization_is_new  whether the organization is a name to confirm
     * @param  string  $explanation  what the filter takes, in a simple sentence
     */
    public function __construct(
        public readonly array $contains,
        public readonly array $excludes,
        public readonly ?string $title,
        public readonly ?string $category,
        public readonly ?int $category_id,
        public readonly ?string $organization,
        public readonly ?int $organization_id,
        public readonly bool $organization_is_new,
        public readonly string $explanation,
    ) {}

    /**
     * The data of a suggestion.
     *
     * @param  BankFilterSuggestion  $suggestion  what `SuggestBankFilter` gave
     */
    public static function fromSuggestion(BankFilterSuggestion $suggestion): self
    {
        return new self(
            contains: $suggestion->contains,
            excludes: $suggestion->excludes,
            title: $suggestion->title,
            category: $suggestion->category,
            category_id: $suggestion->categoryId,
            organization: $suggestion->organization,
            organization_id: $suggestion->organizationId,
            organization_is_new: $suggestion->organizationIsNew(),
            explanation: $suggestion->explanation,
        );
    }
}
