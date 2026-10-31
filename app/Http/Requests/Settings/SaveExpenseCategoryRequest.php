<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Cards\CardType;
use App\Cards\CardTypes;
use App\Models\ExpenseCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The card type of an expense category: only a main category has one, a subcategory takes it from its main one.
 */
final class SaveExpenseCategoryRequest extends FormRequest
{
    /**
     * Only an `admin` changes the categories. The category of another family is already a 404 by then: the address
     * is resolved before the request.
     */
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof ExpenseCategory && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * The rules of the field: empty, or one of the 13 card types, on a main category.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'card_type' => [
                'nullable',
                'string',
                Rule::in(array_map(static fn (CardType $type): string => $type->type(), CardTypes::all())),
                $this->onMainCategory(...),
            ],
        ];
    }

    /**
     * The names of the fields in the messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['card_type' => __('Linked card')];
    }

    /**
     * Rejects a card type on a subcategory, which takes the one of its main category.
     *
     * @param  Closure(string): mixed  $fail
     */
    private function onMainCategory(string $attribute, mixed $value, Closure $fail): void
    {
        $category = $this->route('category');

        if ($category instanceof ExpenseCategory && $category->parent_id !== null) {
            $fail(__('A subcategory takes the card of its main category.'));
        }
    }
}
